"""Drafts a solution with the Google Gemini API (free tier available)."""

from __future__ import annotations

import json
from pathlib import Path

from google import genai
from google.genai import errors, types

from .attachments import classify
from .prompts import GUIDELINES

# Larger files go through the Files API instead of inline bytes.
INLINE_LIMIT_BYTES = 15 * 1024 * 1024

# Gemini has fewer thinking levels than our effort scale.
THINKING_LEVELS = {
    "low": types.ThinkingLevel.LOW,
    "medium": types.ThinkingLevel.MEDIUM,
    "high": types.ThinkingLevel.HIGH,
    "xhigh": types.ThinkingLevel.HIGH,
    "max": types.ThinkingLevel.HIGH,
}

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
            },
        },
        "review_notes": {"type": "string"},
    },
    "required": ["statement_summary", "files", "review_notes"],
}


def _contents(client: genai.Client, context: str, attachments: list[Path]) -> list:
    contents: list = []
    for path in attachments:
        att = classify(path)
        if att.kind in ("text", "unsupported"):
            contents.append(att.text)
            continue
        contents.append(f"Anexo: {path.name}")
        if path.stat().st_size > INLINE_LIMIT_BYTES:
            contents.append(client.files.upload(file=path, config={"mime_type": att.mime}))
        else:
            contents.append(types.Part.from_bytes(data=path.read_bytes(), mime_type=att.mime))
    contents.append(context)
    return contents


def _generate(client: genai.Client, model: str, contents: list, thinking: types.ThinkingConfig | None):
    return client.models.generate_content(
        model=model,
        contents=contents,
        config=types.GenerateContentConfig(
            system_instruction=GUIDELINES,
            response_mime_type="application/json",
            response_json_schema=SCHEMA,
            thinking_config=thinking,
        ),
    )


def solve(context: str, attachments: list[Path], model: str, effort: str, api_key: str) -> dict:
    client = genai.Client(api_key=api_key)
    contents = _contents(client, context, attachments)
    thinking = types.ThinkingConfig(thinking_level=THINKING_LEVELS.get(effort, types.ThinkingLevel.HIGH))

    try:
        try:
            response = _generate(client, model, contents, thinking)
        except errors.ClientError as e:
            # Some models reject thinking levels; retry with the model's default.
            if e.code != 400 or "thinking" not in str(e).lower():
                raise
            response = _generate(client, model, contents, None)
    except errors.ClientError as e:
        if e.code == 429:
            raise RuntimeError("Gemini quota exceeded (free tier limit). Try again later or pick another model.") from e
        raise RuntimeError(f"Gemini rejected the request ({e.code}): {e.message}") from e

    if not response.text:
        reason = response.prompt_feedback.block_reason if response.prompt_feedback else None
        if reason is None and response.candidates:
            reason = response.candidates[0].finish_reason
        raise RuntimeError(f"Gemini returned no answer (reason: {reason}).")
    return json.loads(response.text)
