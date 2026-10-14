"""Drafts a solution by running the local Claude Code CLI (uses the user's subscription).

Claude Code runs headless (`claude -p`) inside the assignment's work directory with only
file tools: it reads the attachments, writes the solution files into draft/ and returns
the summary and review notes as structured output.
"""

from __future__ import annotations

import json
import os
import shutil
import subprocess
import tempfile
import threading
from pathlib import Path
from typing import Callable

from .prompts import GUIDELINES

TOOLS = "Read,Write,Edit,Glob,Grep"
TIMEOUT_SECONDS = 30 * 60
MAX_DRAFT_FILE_BYTES = 2 * 1024 * 1024

SYSTEM = GUIDELINES + """

Como trabalhar neste diretório:
- Os anexos do professor estão em `attachments/`. Leia todos antes de começar e não os altere.
- Grave cada arquivo da resolução em `draft/` com a ferramenta Write (ex.: `draft/index.html`).
- Ao terminar, responda apenas com o resumo do enunciado e as notas de revisão."""

SCHEMA = {
    "type": "object",
    "properties": {
        "statement_summary": {"type": "string"},
        "review_notes": {"type": "string"},
    },
    "required": ["statement_summary", "review_notes"],
    "additionalProperties": False,
}


def find_claude() -> str:
    candidates = [
        os.getenv("CLAUDE_BIN"),
        shutil.which("claude"),
        str(Path.home() / ".local/share/lerd/bin/claude"),
        str(Path.home() / ".local/bin/claude"),
    ]
    for candidate in candidates:
        if candidate and os.access(candidate, os.X_OK):
            return candidate
    raise RuntimeError("Claude Code CLI not found. Set CLAUDE_BIN in worker/.env.")


def _prepare_attachments(attachments: list[Path]) -> list[Path]:
    """Claude Code reads PDFs, images and text natively; convert DOCX to text."""
    readable = []
    for path in attachments:
        if path.suffix.lower() == ".docx":
            import docx
            text_path = path.with_name(path.name + ".txt")
            text_path.write_text("\n".join(p.text for p in docx.Document(str(path)).paragraphs))
            readable.append(text_path)
        else:
            readable.append(path)
    return readable


def _describe_tool(block: dict, work_dir: Path) -> str:
    args = block.get("input") or {}
    target = args.get("file_path") or args.get("pattern") or args.get("path") or ""
    if target.startswith(str(work_dir)):
        target = os.path.relpath(target, work_dir)
    return f"{block.get('name')} {target}".strip()


def _collect_draft(draft_dir: Path, log: Callable[[str], None]) -> list[dict]:
    files = []
    for path in sorted(p for p in draft_dir.rglob("*") if p.is_file()):
        rel = path.relative_to(draft_dir).as_posix()
        if path.stat().st_size > MAX_DRAFT_FILE_BYTES:
            log(f"    skipped draft file (too large): {rel}")
            continue
        try:
            files.append({"path": rel, "content": path.read_text(encoding="utf-8")})
        except UnicodeDecodeError:
            log(f"    skipped draft file (not text): {rel}")
    return files


def solve(
    context: str,
    work_dir: Path,
    attachments: list[Path],
    model: str,
    effort: str,
    log: Callable[[str], None],
) -> dict:
    draft_dir = work_dir / "draft"
    draft_dir.mkdir(parents=True, exist_ok=True)

    readable = _prepare_attachments(attachments)
    listing = "\n".join(f"- {p.relative_to(work_dir).as_posix()}" for p in readable) or "- (nenhum anexo)"
    prompt = f"{context}\n\nAnexos disponíveis:\n{listing}\n\nResolva a atividade gravando os arquivos em draft/."

    cmd = [
        find_claude(), "-p", prompt,
        "--output-format", "stream-json", "--verbose",
        "--json-schema", json.dumps(SCHEMA),
        "--append-system-prompt", SYSTEM,
        "--model", model,
        "--effort", effort,
        "--tools", TOOLS,
        "--restricted", "--strict-mcp-config",
        "--permission-mode", "acceptEdits",
        "--permission-prompts", "none",
        "--no-session-persistence",
    ]

    result = None
    with tempfile.TemporaryFile(mode="w+") as stderr:
        proc = subprocess.Popen(cmd, cwd=work_dir, stdin=subprocess.DEVNULL,
                                stdout=subprocess.PIPE, stderr=stderr, text=True)
        timer = threading.Timer(TIMEOUT_SECONDS, proc.kill)
        timer.start()
        try:
            for line in proc.stdout:
                try:
                    event = json.loads(line)
                except json.JSONDecodeError:
                    continue
                if event.get("type") == "assistant":
                    for block in event.get("message", {}).get("content", []):
                        if block.get("type") == "tool_use" and block.get("name") != "StructuredOutput":
                            log(f"    claude: {_describe_tool(block, work_dir)}")
                elif event.get("type") == "result":
                    result = event
            proc.wait()
        finally:
            timer.cancel()
        stderr.seek(0)
        stderr_tail = stderr.read()[-2000:].strip()

    if result is None:
        reason = "timed out" if proc.returncode == -9 else f"exit code {proc.returncode}"
        raise RuntimeError(f"Claude Code ended without a result ({reason}). {stderr_tail}".strip())
    if result.get("is_error") or result.get("subtype") != "success":
        raise RuntimeError(f"Claude Code failed ({result.get('subtype')}): {result.get('result') or stderr_tail}")

    answer = result.get("structured_output")
    if not isinstance(answer, dict):
        answer = json.loads(result.get("result") or "{}")

    log(f"    claude: done in {result.get('num_turns')} turns, {result.get('duration_ms', 0) / 1000:.0f}s")
    return {
        "statement_summary": answer.get("statement_summary", ""),
        "review_notes": answer.get("review_notes", ""),
        "files": _collect_draft(draft_dir, log),
    }
