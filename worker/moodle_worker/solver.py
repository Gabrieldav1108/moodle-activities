"""Drafts a solution for an assignment with Claude."""

from __future__ import annotations

import base64
import json
import mimetypes
from pathlib import Path

import anthropic

# Models that accept the server-side refusal fallback (Haiku 5.5 does not).
FALLBACK_MODELS = {"claude-opus-5-5", "claude-sonnet-5-5", "claude-fable-5-1"}

# The prompt stays in Portuguese: drafts must be written in pt-BR.
SYSTEM = """Você é um assistente que ajuda um estudante do IFRS a preparar um \
RASCUNHO de resolução de uma atividade do Moodle. O estudante vai revisar, \
entender e ajustar o material antes de entregar, então:

- Resolva a atividade de forma completa e correta, seguindo exatamente o que o \
enunciado pede (formato, linguagem de programação, número de páginas etc.).
- Escreva em português do Brasil, salvo se o enunciado pedir outro idioma.
- Explique o raciocínio em `review_notes` (em Markdown): o que foi assumido, \
pontos que o estudante deve conferir, trechos que dependem de dados que você \
não tinha.
- Se o enunciado pedir arquivos (código, relatório, planilha em CSV...), gere \
cada um em `files` com caminho relativo e conteúdo completo. Para texto \
corrido, use um arquivo `resposta.md`.
- Se faltar informação essencial (ex.: material de aula não anexado), faça o \
melhor possível e deixe isso claro nas notas."""

SCHEMA = {
    "type": "object",
    "properties": {
        "statement_summary": {"type": "string"},
        "files": {
            "type": "array",
            "items": {
                "type": "object",
                "properties": {
                    "path": {"type": "string"},
                    "content": {"type": "string"},
                },
                "required": ["path", "content"],
                "additionalProperties": False,
            },
        },
        "review_notes": {"type": "string"},
    },
    "required": ["statement_summary", "files", "review_notes"],
    "additionalProperties": False,
}

TEXT_EXTENSIONS = {".txt", ".md", ".py", ".java", ".c", ".cpp", ".h", ".js", ".ts",
                   ".html", ".css", ".sql", ".csv", ".json", ".xml", ".php", ".ipynb"}
IMAGE_TYPES = {"image/png", "image/jpeg", "image/gif", "image/webp"}


def _attachment_blocks(path: Path) -> list[dict]:
    ext = path.suffix.lower()
    mime = mimetypes.guess_type(path.name)[0] or ""
    label = {"type": "text", "text": f"Anexo: {path.name}"}
    if ext == ".pdf":
        data = base64.standard_b64encode(path.read_bytes()).decode()
        return [label, {"type": "document", "title": path.name,
                        "source": {"type": "base64", "media_type": "application/pdf", "data": data}}]
    if mime in IMAGE_TYPES:
        data = base64.standard_b64encode(path.read_bytes()).decode()
        return [label, {"type": "image", "source": {"type": "base64", "media_type": mime, "data": data}}]
    if ext == ".docx":
        import docx
        text = "\n".join(p.text for p in docx.Document(str(path)).paragraphs)
        return [{"type": "text", "text": f"Anexo: {path.name}\n\n{text}"}]
    if ext in TEXT_EXTENSIONS:
        text = path.read_text(errors="replace")
        return [{"type": "text", "text": f"Anexo: {path.name}\n\n```\n{text}\n```"}]
    return [{"type": "text", "text": f"Anexo não lido (formato {ext}): {path.name}"}]


def solve(context: str, attachments: list[Path], model: str, effort: str, api_key: str) -> dict:
    client = anthropic.Anthropic(api_key=api_key)
    content: list[dict] = []
    for path in attachments:
        content.extend(_attachment_blocks(path))
    content.append({"type": "text", "text": context})

    extra = {}
    if model in FALLBACK_MODELS:
        extra = {"betas": ["server-side-fallback-2026-07-01"], "fallbacks": "default"}

    with client.beta.messages.stream(
        model=model,
        max_tokens=64000,
        system=SYSTEM,
        thinking={"type": "adaptive"},
        output_config={
            "effort": effort,
            "format": {"type": "json_schema", "schema": SCHEMA},
        },
        messages=[{"role": "user", "content": content}],
        **extra,
    ) as stream:
        message = stream.get_final_message()

    if message.stop_reason == "refusal":
        raise RuntimeError("The model refused the request.")
    if message.stop_reason == "max_tokens":
        raise RuntimeError("Response truncated (max_tokens). Try again.")
    text = next(b.text for b in message.content if b.type == "text")
    return json.loads(text)
