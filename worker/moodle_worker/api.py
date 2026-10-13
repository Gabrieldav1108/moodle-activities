"""HTTP client for the Laravel dashboard's worker API."""

from __future__ import annotations

import json
from contextlib import ExitStack

import requests

from .pipeline import Result


class DashboardClient:
    def __init__(self, base_url: str, token: str, ca_bundle: str | None = None):
        self.base = base_url.rstrip("/") + "/api/worker"
        self.session = requests.Session()
        self.session.headers.update({"Authorization": f"Bearer {token}", "Accept": "application/json"})
        if ca_bundle:
            self.session.verify = ca_bundle

    def _post(self, path: str, timeout: int = 30, **kwargs) -> requests.Response:
        r = self.session.post(f"{self.base}/{path}", timeout=timeout, **kwargs)
        r.raise_for_status()
        return r

    def claim(self) -> dict | None:
        r = self._post("claim")
        return None if r.status_code == 204 else r.json()

    def log(self, run_id: int, lines: list[str]) -> None:
        self._post(f"runs/{run_id}/log", json={"lines": lines})

    def upload_assignment(self, run_id: int, result: Result) -> None:
        with ExitStack() as stack:
            files = [
                ("attachments[]", (path.name, stack.enter_context(open(path, "rb"))))
                for path in result.attachments
            ]
            self._post(
                f"runs/{run_id}/assignments",
                timeout=300,
                data={"meta": json.dumps(result.meta), "draft": json.dumps(result.draft)},
                files=files or None,
            )

    def finish(self, run_id: int, status: str, error: str | None = None) -> None:
        self._post(f"runs/{run_id}/finish", json={"status": status, "error": error})
