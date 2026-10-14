"""Processes one assignment: statement, attachments and AI draft."""

from __future__ import annotations

from dataclasses import dataclass, field
from pathlib import Path
from typing import Callable

from bs4 import BeautifulSoup

from . import api_solver, claude_code, gemini_solver
from .moodle import Assignment, Moodle
from .prompts import QUIZ_NOTE

Log = Callable[[str], None]


@dataclass
class Result:
    meta: dict
    attachments: list[Path] = field(default_factory=list)
    draft: list[dict] = field(default_factory=list)


def process(
    moodle: Moodle,
    assignment: Assignment,
    work_dir: Path,
    engine: str,
    model: str,
    effort: str,
    api_key: str | None,
    log: Log = print,
) -> Result:
    moodle.load_details(assignment)
    statement = BeautifulSoup(assignment.statement_html, "html.parser").get_text("\n", strip=True)

    attachments = []
    for url in assignment.attachment_urls:
        try:
            attachments.append(moodle.download(url, work_dir / "attachments"))
            log(f"    attachment: {attachments[-1].name}")
        except Exception as e:  # noqa: BLE001 - keep going with the other attachments
            log(f"    failed to download {url}: {e}")

    result = Result(
        meta={
            "cmid": assignment.cmid,
            "event_id": assignment.event_id,
            "name": assignment.name,
            "course": assignment.course,
            "module": assignment.module,
            "due_at": assignment.due_at,
            "overdue": assignment.overdue,
            "url": assignment.url,
            "statement_md": statement,
            "statement_html": assignment.statement_html,
            "status": "downloaded",
        },
        attachments=attachments,
    )

    if engine in ("api", "gemini") and not api_key:
        log(f"    no API key set for {engine} on the Automation page: skipping the draft")
        return result

    context = (f"Curso: {assignment.course}\nAtividade ({assignment.module}): {assignment.name}\n"
               f"Prazo: {assignment.due_at_fmt}\n\nEnunciado:\n{statement or '(sem descrição; veja os anexos)'}")
    if assignment.module == "quiz":
        context += QUIZ_NOTE

    log(f"    generating draft via {engine} with {model} (effort {effort})...")
    try:
        if engine == "api":
            answer = api_solver.solve(context, attachments, model, effort, api_key)
        elif engine == "gemini":
            answer = gemini_solver.solve(context, attachments, model, effort, api_key)
        else:
            answer = claude_code.solve(context, work_dir, attachments, model, effort, log)
    except Exception as e:  # noqa: BLE001 - reported back to the dashboard
        log(f"    failed to generate the draft: {e}")
        result.meta.update(status="failed", error=str(e))
        return result

    result.draft = answer["files"]
    result.meta.update(
        status="draft_ready",
        summary=answer["statement_summary"],
        review_notes=answer["review_notes"],
    )
    for file in result.draft:
        log(f"    draft: {file['path']}")
    return result
