"""Minimal client for IFRS Moodle (web session + AJAX service)."""

from __future__ import annotations

import json
import re
import time
from dataclasses import dataclass, field
from pathlib import Path
from urllib.parse import unquote, urlparse

import requests
from bs4 import BeautifulSoup

USER_AGENT = (
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/130.0 Safari/537.36"
)


class MoodleError(RuntimeError):
    pass


@dataclass
class Assignment:
    event_id: int | None
    cmid: int
    name: str
    module: str  # assign, quiz, forum, ...
    course: str
    url: str
    due_at: int | None  # unix timestamp
    overdue: bool
    statement_html: str = ""
    attachment_urls: list[str] = field(default_factory=list)

    @property
    def due_at_fmt(self) -> str:
        if not self.due_at:
            return "-"
        return time.strftime("%d/%m/%Y %H:%M", time.localtime(self.due_at))

    @classmethod
    def from_target(cls, target: dict) -> "Assignment":
        """Rebuild an assignment the dashboard already knows (used by redo runs)."""
        return cls(
            event_id=target.get("event_id"),
            cmid=target["cmid"],
            name=target["name"],
            module=target["module"],
            course=target["course"],
            url=target["url"],
            due_at=target.get("due_at"),
            overdue=bool(target.get("overdue")),
        )


class Moodle:
    def __init__(self, base_url: str):
        self.base = base_url.rstrip("/")
        self.session = requests.Session()
        self.session.headers["User-Agent"] = USER_AGENT
        self.sesskey: str | None = None

    # ------------------------------------------------------------------ login
    def login(self, username: str, password: str) -> None:
        r = self.session.get(f"{self.base}/login/index.php", timeout=30)
        r.raise_for_status()
        m = re.search(r'name="logintoken" value="([^"]+)"', r.text)
        data = {"username": username, "password": password, "anchor": ""}
        if m:
            data["logintoken"] = m.group(1)
        r = self.session.post(f"{self.base}/login/index.php", data=data, timeout=30)
        r.raise_for_status()
        if "/login/index.php" in r.url or 'id="loginerrormessage"' in r.text:
            raise MoodleError("Login failed: check username and password.")
        self._load_sesskey(r.text)

    def _load_sesskey(self, html: str | None = None) -> None:
        if html is None or '"sesskey"' not in html:
            html = self.session.get(f"{self.base}/my/", timeout=30).text
        m = re.search(r'"sesskey":"([^"]+)"', html)
        if not m:
            raise MoodleError("Could not find the sesskey (invalid session?).")
        self.sesskey = m.group(1)

    # ------------------------------------------------------------------- ajax
    def ajax(self, method: str, args: dict) -> dict:
        r = self.session.post(
            f"{self.base}/lib/ajax/service.php",
            params={"sesskey": self.sesskey, "info": method},
            data=json.dumps([{"index": 0, "methodname": method, "args": args}]),
            headers={"Content-Type": "application/json"},
            timeout=30,
        )
        r.raise_for_status()
        resp = r.json()[0]
        if resp.get("error"):
            exc = resp.get("exception") or {}
            raise MoodleError(f"{method}: {exc.get('message', resp)}")
        return resp["data"]

    # ---------------------------------------------------------------- pending
    def pending(self, days_back: int = 30, days_ahead: int = 120) -> list[Assignment]:
        """Same query the 'Timeline' block on the /my/ dashboard uses."""
        now = int(time.time())
        args = {
            "timesortfrom": now - days_back * 86400,
            "timesortto": now + days_ahead * 86400,
            "limitnum": 50,
            "limittononsuspendedevents": True,
        }
        assignments: list[Assignment] = []
        while True:
            data = self.ajax("core_calendar_get_action_events_by_timesort", args)
            events = data.get("events", [])
            for ev in events:
                m = re.search(r"[?&]id=(\d+)", ev.get("url") or "")
                if not m:
                    continue
                course = ev.get("course") or {}
                assignments.append(
                    Assignment(
                        event_id=ev["id"],
                        cmid=int(m.group(1)),
                        name=ev.get("activityname") or ev.get("name", ""),
                        module=ev.get("modulename", ""),
                        course=course.get("fullname", ""),
                        url=ev.get("url", ""),
                        due_at=ev.get("timesort"),
                        overdue=bool(ev.get("overdue")),
                    )
                )
            if len(events) < args["limitnum"] or not data.get("lastid"):
                break
            args["aftereventid"] = data["lastid"]
        return assignments

    # ---------------------------------------------------------------- details
    def load_details(self, assignment: Assignment) -> None:
        r = self.session.get(assignment.url, timeout=30)
        r.raise_for_status()
        soup = BeautifulSoup(r.text, "html.parser")
        main = soup.select_one("#region-main") or soup
        parts = []
        for selector in (".activity-description", "#intro", ".box.generalbox"):
            parts = main.select(selector)
            if parts:
                break
        assignment.statement_html = "\n".join(str(p) for p in parts) or str(main)

        urls = []
        for a in main.select('a[href*="/pluginfile.php/"]'):
            href = a["href"].split("?")[0]
            if href not in urls and "/submission_files/" not in href:
                urls.append(href)
        assignment.attachment_urls = urls

    def download(self, url: str, dest_dir: Path) -> Path:
        dest_dir.mkdir(parents=True, exist_ok=True)
        name = unquote(Path(urlparse(url).path).name) or "file"
        name = re.sub(r"[^\w.\- ]", "_", name)
        dest = dest_dir / name
        with self.session.get(url, timeout=120, stream=True) as r:
            r.raise_for_status()
            with open(dest, "wb") as f:
                for chunk in r.iter_content(65536):
                    f.write(chunk)
        return dest
