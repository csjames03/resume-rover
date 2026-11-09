# Resume Rover

A personal job-search command center: upload your résumé, pull openings from official job feeds, see how well each role fits **and why**, draft honest tailored résumés, and track every application.

Built from the plan in [`docs/AI_Job_Search_Platform_Project_Plan.pdf`](docs/AI_Job_Search_Platform_Project_Plan.pdf). See [`docs/architecture.md`](docs/architecture.md) for how it works and the decisions behind it, and [`docs/workflow.md`](docs/workflow.md) for how changes are made.

## Highlights

- **Explainable scoring.** Requirements are extracted once per posting and scored in plain PHP, so every point gained or lost shows up as a strength or a gap. AI writes the explanation but can't change the number.
- **Honest tailoring.** AI may only reword bullets you already have and must cite them. New numbers or skills are flagged for you to confirm.
- **Resilient AI layer.** Gemini, Groq and OpenRouter behind one gateway, with failover, exponential-backoff cooldowns and metadata-only logging.
- **Nine official job feeds** (Greenhouse, Lever, Ashby, Recruitee, Remotive and more): idempotent ingestion, cross-source de-duplication, per-source rate limits.
- **Apply by email** through your own Gmail with the send-only scope and encrypted tokens.
- **Quality gates.** PHPUnit on PostgreSQL, Larastan level 7, Pint and TypeScript strict mode, all in CI.

## Stack

Laravel 13 · Inertia 3 · React 19 + TypeScript · Tailwind 4 + shadcn/ui · PostgreSQL · Laravel queues · Fortify auth

## Requirements

- PHP 8.3+ with `pdo_pgsql`, `zip`, `intl`, `fileinfo`
- Composer 2, Node 22+
- PostgreSQL 15+

## Setup

```bash
composer install
npm install
cp .env.example .env        # then fill in DB_* (and optionally an AI key)
php artisan key:generate
php artisan migrate --seed  # creates tables, the skills dictionary and 4 starter job sources
npm run build
```

`--seed` also creates `test@example.com` / `password` (an admin) when `APP_ENV=local`.

Job sources are shared by every account, so only admins can change them. Grant it from the command line:

```bash
php artisan users:admin you@example.com            # --revoke to remove
```

On a public deploy, set `REGISTRATION_ENABLED=false` so strangers can't sign up and spend your AI quota.

## Running it

```bash
composer dev                    # web server + queue worker + Vite, together
php artisan postings:fetch --now  # pull jobs from every enabled source right away
```

Parsing, fetching, scoring and AI work all run on the queue, so the worker must be running. In production, also run the scheduler (`php artisan schedule:run` every minute); it refreshes sources hourly within each source's allowed interval.

### AI (optional)

Scoring works without AI. Add any of `GEMINI_API_KEY`, `GROQ_API_KEY` or `OPENROUTER_API_KEY` to `.env` to enable:

- smarter résumé parsing (falls back to the rule-based parser),
- plain-language score explanations,
- tailored résumé drafts.

Providers are tried in `AI_PROVIDER_PRIORITY` order. A rate-limited or failing provider is benched with exponential backoff and the next one takes over. Settings → AI providers shows the live state.

> After changing `.env`, restart the queue worker. Workers keep the environment they started with.

### Gmail (optional): apply by email

On any job, **Draft application email** writes a draft from your résumé. You review and edit it in the app, then click **Send**: it goes out from your own Gmail with your résumé attached, and the job is marked Applied. The app only asks for permission to **send** (`gmail.send`); it can't read your inbox.

One-time setup (about 5 minutes):

1. Go to [console.cloud.google.com](https://console.cloud.google.com), create a project, and enable the **Gmail API** (APIs & Services → Library).
2. **OAuth consent screen**: choose _External_, fill in the app name and your email, and add yourself under **Test users**.
3. **Credentials → Create credentials → OAuth client ID**: type _Web application_, authorized redirect URI `http://localhost:8000/settings/email/callback` (your `APP_URL` + `/settings/email/callback`).
4. Put the client ID and secret in `.env` as `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`.
5. Open the app **at the same address as `APP_URL`** and go to Settings → Email → Connect Gmail.

While the Google app is in _Testing_ mode, Google expires the connection after 7 days; the app will ask you to reconnect.

## Useful commands

| Command                                           | What it does                                                                    |
| ------------------------------------------------- | ------------------------------------------------------------------------------- |
| `php artisan postings:fetch [id] [--due] [--now]` | Fetch job sources (queued unless `--now`)                                       |
| `php artisan postings:reanalyze`                  | Re-extract requirements after editing `database/data/skills.php`, then re-score |
| `php artisan db:seed --class=SkillSeeder`         | Load dictionary changes                                                         |
| `php artisan users:admin {email} [--revoke]`      | Grant or remove admin rights (managing job sources)                             |
| `composer test`                                   | Pint, Larastan (level 7) and the PHPUnit suite                                  |
| `npm run check` / `npm run types:check`           | Frontend lint/format and TypeScript                                             |

Tests run against PostgreSQL (`resume_rover_test`), because the app relies on Postgres features.

## Contributing

Every change follows [`docs/workflow.md`](docs/workflow.md): issue → branch → failing test → build → `composer ci:check` → pull request.

## Not built yet

These were deferred, as the plan's MVP section recommends: reading recruiter replies from Gmail (that needs the restricted read scope), PDF/DOCX export of tailored résumés (Markdown download works today), and multi-user/team features.
