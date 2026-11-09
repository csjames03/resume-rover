# Resume Rover: instructions for AI assistants

Portfolio project: a Laravel 13 + Inertia 3 + React 19 job-search app on PostgreSQL. Code quality is part of the product.

Read before changing anything:

- `docs/workflow.md`: the implementation loop, where each kind of code goes, UI rules, commit style.
- `docs/architecture.md`: how scoring, AI, tailoring, job sources and Gmail work, and why.

Rules that matter most:

- Thin controllers. Validation in Form Requests, ownership in Policies, logic in `app/Services`, slow work in queue jobs.
- Scores are computed in PHP (`MatchScorer`). AI only explains, tailors and drafts, and must not invent experience (`ProvenanceGuard`, `ClaimChecker`).
- Never print or commit `.env` values. Keys are read only in `config/*.php`.
- Tests run on PostgreSQL (`resume_rover_test`). External HTTP is always faked.
- After route changes: `php artisan wayfinder:generate --with-form`.
- UI: theme tokens only, shared components (`PageHeader`, `EmptyState`, `StatusBadge`, `ScoreRing`, `CompanyAvatar`), lucide icons, works in dark mode.
- Done means `composer ci:check` passes. Commits follow Conventional Commits on a feature branch.
