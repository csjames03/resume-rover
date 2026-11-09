# Architecture

## The core idea

A job's score has to be **explainable and reproducible**, so the work is split three ways:

1. **Extract once per job.** When a posting arrives, `RequirementExtractor` lists what it asks for (skills, must-have vs nice-to-have, years) and stores it in `job_requirements`. This is the same for every user, so it never runs per user.
2. **Score in plain PHP.** `MatchScorer` compares the candidate's profile with those requirements. Same input, same score; every point gained or lost becomes a strength or a gap. Weights and score bands live in `config/matching.php`.
3. **AI explains; it doesn't decide.** `MatchExplainer` turns the computed strengths and gaps into prose. It can't change the score.

Free-tier limits are handled in the gateway, not the features. Each response's `x-ratelimit-*` headers become a `RateLimitSnapshot` (cached per provider). Before calling a provider, the gateway checks that snapshot and skips a provider that would refuse the request. It also trims `max_tokens` to fit the per-minute token limit. `AiAvailability` combines that with each user's daily allowance (`UserAiAllowance`) and is checked when the button is pressed, so users get an honest "try again in about 2 minutes" instead of a failed job later.

Tailoring follows the same rule: the model may only reword bullets that already exist, must cite the bullet ids it used, and `ProvenanceGuard` drops anything uncited and flags new numbers or skills for the user to confirm.

## Request and job flow

```
Browser (React via Inertia)
   │
   ▼
Controllers ──► Form Requests (validation) ──► Policies (ownership)
   │
   ▼
Services (business logic) ──► Models (Eloquent, PostgreSQL)
   │
   └─► Queue jobs ──► same services, in the background
```

| Trigger                          | What happens                                                                                                                                                                                    |
| -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Résumé upload                    | `ResumeUploader` stores the file on the private disk → `ParseResume` → `ResumeParsingService` (AI parser, falling back to `HeuristicResumeParser`) → sections, bullets, skills → `ScoreMatches` |
| Profile edit                     | `ResumeProfileEditor` bumps `resumes.profile_version` → `ScoreMatches` (unique per user, so a burst of edits queues one re-score)                                                               |
| Scheduler / "Fetch now"          | `IngestJobSource` → adapter fetches and normalizes → `JobIngestor` upserts, de-duplicates, closes vanished jobs, extracts requirements → `ScoreMatches` for new/changed postings                |
| "Explain this score"             | `ExplainMatch` → `AiGateway`                                                                                                                                                                    |
| "Generate draft"                 | `GenerateTailoredResume` → `AiGateway` → `ProvenanceGuard`                                                                                                                                      |
| "Draft application email" → Send | `ApplicationEmailDrafter` (AI or template, claims checked) → user edits → `ApplicationEmailSender` → `GmailMailer` → tracker marks Applied                                                      |

Long work never blocks a request. Pages poll (`usePoll`) only while something is pending.

## Folder map

```
app/
  Enums/                 String-backed enums with behavior (labels, rules, adapter lookup)
  Models/                Eloquent models; relationships, casts, small domain helpers
  Http/
    Controllers/         Thin: validate → call a service → redirect or render
    Requests/            All input validation
    Resources/           Exactly what each page receives (nothing leaks by accident)
  Policies/              Owner-only access to résumés, applications, tailored drafts
  Queries/               JobPostingSearch: the jobs table's filters and sorting
  Jobs/                  Queue jobs; they only load models and call services
  Console/Commands/      postings:fetch, postings:reanalyze
  Services/
    Ai/                  AiGateway, provider registry, cooldowns, OpenAI-compatible adapter
    JobSources/          Adapter contract, Greenhouse/Lever/Remotive adapters, JobIngestor
    Matching/            RequirementExtractor, CandidateProfile, MatchScorer, MatchService, MatchExplainer
    Resumes/             Upload, text extraction, parsers, profile editing
    Skills/              SkillMatcher (dictionary + aliases → skill ids)
    Tailoring/           ResumeTailor, ProvenanceGuard, TailoredContent
    Applications/        ApplicationTracker (every change writes history)
    Email/               Google OAuth (send-only), GmailMailer, ApplicationEmailDrafter, ApplicationEmailSender
config/ai.php            Providers, failover order, per-task settings, cooldowns
config/matching.php      Weights, penalties, score bands
database/data/skills.php The skills dictionary (edit, then re-seed and reanalyze)
resources/js/
  pages/                 One file per Inertia page
  components/            Shared UI; feature folders for jobs/ and resume/
  types/models.ts        Mirrors app/Http/Resources
```

## Data model

| Table                                     | Holds                                                                                              |
| ----------------------------------------- | -------------------------------------------------------------------------------------------------- |
| `users`, `user_preferences`               | Account; target titles, locations, remote preference, level, excluded keywords                     |
| `resumes`                                 | Uploaded file metadata, extracted text, parse status, `profile_version`, active flag               |
| `resume_sections`, `resume_bullets`       | Parsed entries. Bullets have their own ids so tailored drafts can cite them                        |
| `skills`, `skill_aliases`, `resume_skill` | Dictionary ("JS" = "JavaScript") and the résumé's skills (parsed or added by hand)                 |
| `job_sources`                             | Configured feeds, refresh interval, last fetch status                                              |
| `job_postings`                            | Normalized ads, shared by all users. Named so it doesn't collide with Laravel's queue `jobs` table |
| `job_requirements`                        | Extracted requirements per posting                                                                 |
| `job_matches`                             | A user's score per posting, strengths/gaps JSON, the profile version it used, AI explanation       |
| `applications`, `application_events`      | Tracking record, plus append-only history                                                          |
| `tailored_resumes`                        | Versioned drafts with evidence links and flagged claims                                            |
| `ai_provider_events`                      | One row per AI call: provider, outcome, latency, tokens. Never prompts or content                  |
| `email_connections`                       | The connected Gmail account; OAuth tokens encrypted at rest, never serialized                      |
| `application_emails`                      | Drafts and sent application emails, with claim flags and the Gmail message id                      |

Enum-like columns are plain strings cast to PHP enums, because PostgreSQL enum constraints are painful to change.

## Decisions that differ from the PDF plan

| Plan                                         | Built                                    | Why                                                                                                                                        |
| -------------------------------------------- | ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Separate Next.js app + Laravel API + Sanctum | One Laravel app with React via Inertia   | Same React UI with one deploy, no CORS, and no cross-domain cookie problems. Controllers stay API-shaped, so a JSON API can be added later |
| Redis queue/cache                            | Database driver locally                  | Zero extra services in development. Switching is a `.env` change, and cooldowns use the cache, so they move with it                        |
| Generic `ai_tasks` table                     | Status columns on the records themselves | `resumes.parse_status`, `job_matches.explanation_status`, `tailored_resumes.status` keep state next to the data                            |
| 11 application statuses                      | 7 statuses + `archived_at`               | "Saved/To review" and "Tailoring/Ready" overlapped; archiving is independent of outcome                                                    |
| Gmail reading + syncing                      | Send-only Gmail; drafts live in the app  | `gmail.send` is enough to apply by email and avoids the restricted read/compose scopes that need a Google security review                  |

## Safety and compliance

- Résumés live on the private disk; every personal record is owner-only through policies.
- API keys stay server-side; AI logs hold metadata only.
- Job sources are official public feeds. Each driver has a minimum refresh interval (Remotive: 6 h, per its terms), Remotive jobs are shown "via Remotive" with a link back, and no HTML from job boards reaches the browser.
- Nothing is sent anywhere automatically. Tailored drafts need explicit approval before download, and application emails go out only when the user clicks Send and confirms.
- Gmail access is send-only (`gmail.send`). Tokens are encrypted, refreshed automatically, and revoked at Google on disconnect.
- Job sources are shared, so changing them takes the `manage-job-sources` gate (`users.is_admin`). Admin rights are granted only with `php artisan users:admin`: email verification is off, so an email allowlist could be claimed by whoever registers first.
- Every response carries a nonce-based Content Security Policy (only scripts the app rendered can run), `X-Frame-Options: DENY`, `nosniff`, a strict referrer policy, and HSTS over HTTPS (`SecurityHeaders` middleware).
- Sessions are encrypted, cookies are HTTP-only and `SameSite=Lax`, and HTTPS-only by default in production. Registration can be turned off with `REGISTRATION_ENABLED=false`.
- Cookies the browser can edit (`appearance`) are checked against an allowlist before they reach a template.
- The database connection is pinned to UTC (`DB_TIMEZONE`), so timestamps are never shifted by the server's local zone.
