# Development workflow

How a change goes from idea to `main`. The goal is a history a reviewer (or a hiring manager) can read: small branches, one purpose per PR, green checks every time.

## The loop

```
1. Issue      → write down what and why (feature, bug or chore template)
2. Branch     → feat/…, fix/…, chore/…, docs/… off an up-to-date main
3. Design     → decide which layers change (see "Where code goes")
4. Test first → a failing feature test that describes the behavior
5. Build      → backend inside out, then the page
6. Check      → composer ci:check (the same thing CI runs)
7. PR         → fill the template, add a screenshot for UI changes
8. Merge      → squash merge, delete the branch
```

### 1. Issue

Every change starts as a GitHub issue using a template in `.github/ISSUE_TEMPLATE`. Write the **outcome** ("I can filter jobs by salary"), not the implementation. Add acceptance criteria you can test.

### 2. Branch

```bash
git switch main && git pull
git switch -c feat/salary-filter     # feat | fix | chore | docs | refactor | test
```

One branch = one issue. If you find an unrelated problem, open another issue instead of fixing it on the side.

### 3. Design: where code goes

Walk the request from the outside in and list the files you expect to touch before writing any code.

| Concern                     | Lives in                                    | Rule                                                                |
| --------------------------- | ------------------------------------------- | ------------------------------------------------------------------- |
| URL                         | `routes/web.php`, `routes/settings.php`     | Resourceful names; run `php artisan wayfinder:generate --with-form` |
| Input validation            | `app/Http/Requests`                         | Never validate in controllers                                       |
| Who may do it               | `app/Policies`                              | Personal records are owner-only                                     |
| HTTP glue                   | `app/Http/Controllers`                      | Thin: validate → call a service → redirect or render                |
| Business rules              | `app/Services/<Domain>`                     | Plain classes, injected; this is where the thinking goes            |
| Slow or external work       | `app/Jobs`                                  | Load models, call a service, nothing else                           |
| Data shape sent to the page | `app/Http/Resources` + `resources/js/types` | Keep the TypeScript type in sync with the resource                  |
| Tables                      | `database/migrations` + `app/Models`        | New migration per change; never edit a shipped one                  |
| Fixed choices               | `app/Enums`                                 | String-backed enums, not PostgreSQL enums                           |
| Tunable numbers             | `config/*.php`                              | Weights, limits, timeouts; no magic numbers in services             |
| Page                        | `resources/js/pages`                        | One file per Inertia page                                           |
| Reusable UI                 | `resources/js/components`                   | Feature folders (`jobs/`, `resume/`); shadcn primitives in `ui/`    |

If the change affects scoring, AI or job sources, read the matching section of [architecture.md](architecture.md) first. Those areas have rules (AI explains but never decides; tailoring must cite bullet ids; sources must be official feeds).

### 4. Test first

Write a feature test under `tests/Feature/<Area>` that fails for the right reason.

- HTTP behavior: hit the route as a user and assert the redirect, the Inertia component and its props, and the database.
- Services: call the service directly with factories.
- External calls (AI, Gmail, job boards): `Http::fake()`. Tests never touch the network.
- Pure logic with no framework (parsers, date ranges): `tests/Unit`.

```bash
php artisan test --filter=SalaryFilter
```

### 5. Build

Backend inside out: migration → model → service → request/policy → controller → route → resource. Then the page.

UI rules (from the design pass):

- Use theme tokens (`bg-card`, `text-muted-foreground`, `text-primary`, `fit-*`), never raw hex.
- Page wrapper: `mx-auto w-full max-w-7xl p-4 md:p-8`, with `PageHeader` at the top.
- Reuse `EmptyState`, `StatusBadge`, `FitBadge`/`ScoreRing` and `CompanyAvatar`. Don't restyle them per page.
- Icons come from `lucide-react` only. Icon-only buttons need an `aria-label`.
- Color is never the only signal: pair it with a label or number.
- Check light **and** dark mode, and a 375 px wide window.

### 6. Check

```bash
composer ci:check     # frontend lint + types, Pint, Larastan level 7, PHPUnit
npm run check:fix     # auto-fix formatting and lint
vendor/bin/pint       # auto-fix PHP style
```

Don't open a PR while this is red. Don't silence Larastan with ignores; fix the type.

### 7. Commit and pull request

Commits use [Conventional Commits](https://www.conventionalcommits.org):

```
feat(jobs): filter postings by salary range
fix(resume): keep "Inc." in company names when splitting header lines
chore(deps): bump laravel/framework
```

Present tense, under 72 characters, with the _why_ in the body when it isn't obvious. Push and open a PR; the template asks for what changed, how you tested it and a screenshot for UI changes.

### 8. Merge

Squash merge once CI is green, then delete the branch. `main` always builds and always passes.

## Releases

Tag milestones so the portfolio shows progress: `git tag -a v0.2.0 -m "Gmail apply-by-email"`. Note user-visible changes in the GitHub release.

## Running locally

```bash
composer dev                       # server + queue worker + Vite
php artisan postings:fetch --now   # pull jobs right away
```

After changing `.env`, restart `composer dev`; the queue worker keeps the old environment.

## Secrets

`.env` is never committed. New settings go in `.env.example` with an empty value and a comment. API keys are only read in `config/*.php`, never with `env()` elsewhere, and never logged or sent to the browser.
