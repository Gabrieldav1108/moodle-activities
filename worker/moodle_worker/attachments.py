"""Classifies attachments for the API-based solvers."""

from __future__ import annotations

import mimetypes
from dataclasses import dataclass
from pathlib import Path

TEXT_EXTENSIONS = {".txt", ".md", ".py", ".java", ".c", ".cpp", ".h", ".js", ".ts",
                   ".html", ".css", ".sql", ".csv", ".json", ".xml", ".php", ".ipynb"}
IMAGE_TYPES = {"image/png", "image/jpeg", "image/gif", "image/webp"}


@dataclass
class Attachment:
    path: Path
    kind: str  # "pdf", "image", "text" or "unsupported"
    mime: str = ""
    text: str = ""


def classify(path: Path) -> Attachment:
    ext = path.suffix.lower()
    mime = mimetypes.guess_type(path.name)[0] or ""
    if ext == ".pdf":
        return Attachment(path, "pdf", "application/pdf")
    if mime in IMAGE_TYPES:
        return Attachment(path, "image", mime)
    if ext == ".docx":
        import docx
        text = "\n".join(p.text for p in docx.Document(str(path)).paragraphs)
        return Attachment(path, "text", text=f"Anexo: {path.name}\n\n{text}")
    if ext in TEXT_EXTENSIONS:
        text = path.read_text(errors="replace")
        return Attachment(path, "text", text=f"Anexo: {path.name}\n\n```\n{text}\n```")
    return Attachment(path, "unsupported", text=f"Anexo não lido (formato {ext}): {path.name}")
