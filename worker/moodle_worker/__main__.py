"""CLI: python -m moodle_worker {run,list}"""

from __future__ import annotations

import argparse
import os
import sys
from pathlib import Path

from dotenv import load_dotenv

from .moodle import Moodle, MoodleError

ROOT = Path(__file__).resolve().parent.parent


def cmd_run(args) -> None:
    from .api import DashboardClient
    from .runner import run_forever

    url, token = os.getenv("DASHBOARD_URL"), os.getenv("WORKER_TOKEN")
    if not url or not token:
        sys.exit("Set DASHBOARD_URL and WORKER_TOKEN in worker/.env (see .env.example).")
    run_forever(DashboardClient(url, token, os.getenv("DASHBOARD_CA") or None), args.interval)


def cmd_list(args) -> None:
    """Local debugging: list pending assignments using MOODLE_* from .env."""
    username, password = os.getenv("MOODLE_USERNAME"), os.getenv("MOODLE_PASSWORD")
    if not username or not password:
        sys.exit("Set MOODLE_USERNAME and MOODLE_PASSWORD in worker/.env to use 'list'.")
    moodle = Moodle(os.getenv("MOODLE_URL", "https://presencial.ifrs.edu.br"))
    try:
        moodle.login(username, password)
        assignments = moodle.pending(args.days_back, args.days_ahead)
    except MoodleError as e:
        sys.exit(f"Error: {e}")
    for a in assignments:
        status = "OVERDUE" if a.overdue else "pending"
        print(f"{a.cmid:>7}  {a.due_at_fmt}  {status:<7}  {a.module:<7} {a.course[:35]:<35}  {a.name}")
    if not assignments:
        print("No pending assignments.")


def main() -> None:
    load_dotenv(ROOT / ".env")
    parser = argparse.ArgumentParser(prog="moodle_worker", description="IFRS Moodle worker")
    sub = parser.add_subparsers(dest="cmd", required=True)
    run = sub.add_parser("run", help="poll the dashboard and process runs")
    run.add_argument("--interval", type=float, default=10.0, help="seconds between polls")
    lst = sub.add_parser("list", help="list pending assignments (debug)")
    lst.add_argument("--days-back", type=int, default=30)
    lst.add_argument("--days-ahead", type=int, default=120)
    args = parser.parse_args()
    {"run": cmd_run, "list": cmd_list}[args.cmd](args)


if __name__ == "__main__":
    main()
