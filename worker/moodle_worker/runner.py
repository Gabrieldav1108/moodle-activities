"""Worker loop: claims runs from the dashboard and processes them."""

from __future__ import annotations

import sys
import tempfile
import threading
import time
import traceback
from pathlib import Path

import requests

from .api import DashboardClient
from .moodle import Assignment, Moodle
from .pipeline import process


class RunLogger:
    """Buffers log lines and ships them every few seconds (doubles as a heartbeat)."""

    def __init__(self, client: DashboardClient, run_id: int, interval: float = 3.0):
        self.client = client
        self.run_id = run_id
        self.interval = interval
        self.lines: list[str] = []
        self.lock = threading.Lock()
        self.stop = threading.Event()
        self.thread = threading.Thread(target=self._loop, daemon=True)

    def __enter__(self) -> "RunLogger":
        self.thread.start()
        return self

    def __exit__(self, *exc) -> None:
        self.stop.set()
        self.thread.join()
        self.flush()

    def __call__(self, line: str) -> None:
        print(line, flush=True)
        with self.lock:
            self.lines.append(f"[{time.strftime('%H:%M:%S')}] {line}")

    def _loop(self) -> None:
        while not self.stop.wait(self.interval):
            self.flush()

    def flush(self) -> None:
        with self.lock:
            lines, self.lines = self.lines, []
        try:
            self.client.log(self.run_id, lines)
        except requests.RequestException as e:
            print(f"failed to send log: {e}", file=sys.stderr)
            with self.lock:
                self.lines = lines + self.lines


def select_assignments(moodle: Moodle, job: dict, log: RunLogger) -> list[Assignment]:
    pending = moodle.pending(job["days_back"], job["days_ahead"])
    log(f"{len(pending)} pending assignment(s) on Moodle")

    target = job.get("target")
    if target:
        # Prefer fresh data from the timeline; fall back to what the dashboard knows.
        match = next((a for a in pending if a.cmid == target["cmid"]), None)
        return [match or Assignment.from_target(target)]

    if job["force"]:
        return pending
    done = set(job["done_cmids"])
    selected = [a for a in pending if a.cmid not in done]
    if skipped := len(pending) - len(selected):
        log(f"skipping {skipped} assignment(s) that already have a draft")
    return selected


def handle(client: DashboardClient, job: dict) -> None:
    run_id = job["run"]["id"]
    with RunLogger(client, run_id) as log:
        log(f"run #{run_id} ({job['run']['type']}) · {job['model']} · effort {job['effort']}")
        try:
            creds = job["moodle"]
            if not creds.get("username") or not creds.get("password"):
                raise RuntimeError("Moodle credentials are not set on the Automation page.")

            moodle = Moodle(creds["url"])
            moodle.login(creds["username"], creds["password"])
            log("logged in to Moodle")

            failures = 0
            assignments = select_assignments(moodle, job, log)
            for assignment in assignments:
                log(f"- {assignment.name} ({assignment.course})")
                try:
                    with tempfile.TemporaryDirectory(prefix="moodle-worker-") as tmp:
                        result = process(moodle, assignment, Path(tmp), job["model"], job["effort"],
                                         job.get("anthropic_api_key"), log)
                        client.upload_assignment(run_id, result)
                    if result.meta["status"] == "failed":
                        failures += 1
                except Exception as e:  # noqa: BLE001 - one assignment must not stop the run
                    failures += 1
                    log(f"    error: {e}")

            log(f"done: {len(assignments)} processed, {failures} with errors")
        except Exception as e:  # noqa: BLE001
            log(f"run failed: {e}")
            traceback.print_exc()
            log.flush()
            client.finish(run_id, "failed", str(e))
            return

    client.finish(run_id, "completed")


def run_forever(client: DashboardClient, interval: float = 10.0) -> None:
    print(f"worker started, polling {client.base} every {interval:.0f}s", flush=True)
    while True:
        try:
            job = client.claim()
        except requests.RequestException as e:
            print(f"dashboard unreachable: {e}", file=sys.stderr, flush=True)
            job = None
        if job:
            handle(client, job)
        else:
            time.sleep(interval)
