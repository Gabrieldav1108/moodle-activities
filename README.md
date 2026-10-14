# Painel IFRS

Laravel dashboard (Livewire 4 + Flux) that schedules and reviews AI-drafted solutions for
pending assignments on IFRS Moodle (`presencial.ifrs.edu.br`). A Python worker does the
Moodle access and calls Claude; Laravel owns the schedule, settings, credentials and UI.
Nothing is ever submitted to Moodle: you review the draft and submit it yourself.

```
Laravel (Lerd PHP container)                 Python worker (host, Lerd worker)
  scheduler ─ creates Run at configured times    polls POST /api/worker/claim every 10 s
  UI ─ "Run now" / "Redo" ─ creates Run          logs in to Moodle, fetches pending items,
  /api/worker/* (Bearer WORKER_TOKEN)   ◄──────  drafts with Claude, uploads results
```

## Setup

```bash
lerd link                       # https://ifrs-painel.test
php artisan migrate --seed       # owner user (SEED_USER_* in .env; random password if new)
lerd npm install && lerd npm run build

# Python worker
cd worker
python3 -m venv .venv && .venv/bin/pip install -r requirements.txt
cp .env.example .env            # WORKER_TOKEN = same value as in ../.env, DASHBOARD_CA = $(mkcert -CAROOT)/rootCA.pem
cd ..

lerd schedule:start             # Laravel scheduler (queues runs at the configured times)
lerd worker start moodle-worker # Python worker (host command, asks for approval once)
```

Then open https://ifrs-painel.test, log in and fill in **Automation**:
run times, weekdays, model, effort and the Moodle credentials (secrets are stored
encrypted with `APP_KEY`).

## Draft engines

Chosen on the Automation page:

- **Claude Code on this machine** (default): the worker runs `claude -p` headless in a
  temporary directory with only file tools (`Read, Write, Edit, Glob, Grep`, `--restricted`,
  no Bash/web/MCP). Claude reads `attachments/`, writes the solution into `draft/` and
  returns the summary and review notes as structured output. Uses your Claude
  subscription limits; no API key needed. The CLI must be logged in (`claude` once).
- **Anthropic API**: single Messages API call with the attachments; needs an API key
  and is billed per token.
- **Google Gemini** (free tier): single `generate_content` call with PDFs/images inline
  and a JSON schema for the answer. Get a free key at https://aistudio.google.com/apikey.
  Free-tier requests are rate limited and Google may use them to improve its products.

The model field is free text with suggestions per provider, so new model IDs work
without code changes. Effort maps to Gemini's thinking level (low/medium/high).

## Pages

- **Dashboard**: worker status, next runs, last run, open assignments, "Run now".
- **Assignments**: statement, attachments, review notes, draft editor, `.zip` download,
  redo with another model/effort, mark as reviewed/submitted.
- **Runs**: history and live log of each run.

## Worker CLI

```bash
cd worker
.venv/bin/python -m moodle_worker run    # same as the Lerd worker (worker/run.py)
.venv/bin/python -m moodle_worker list   # debug: list pending items using MOODLE_* from worker/.env
```

Logs: `journalctl --user -u lerd-moodle-worker-ifrs-painel -f`

## Tests

```bash
php artisan test
vendor/bin/phpstan analyse
```
