"""Entry point that works from any cwd: `worker/.venv/bin/python worker/run.py run`.

Running a script puts its directory on sys.path, so the package resolves without `cd`.
"""

from moodle_worker.__main__ import main

if __name__ == "__main__":
    main()
