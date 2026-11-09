# CLAUDE.md

Enrichment Lookup Coordinator — shares a 10-lookup/day threat-intel provider quota via leases, a FIFO queue and idempotency.

## Source of truth
- `docs/requirements.md` is authoritative. If code or a request conflicts with it, say so before changing anything.
- Build the **core first**: request → lease (5 min) → run → expiry/promotion → never exceed quota (≈200 concurrent). Nothing beyond it until the core works and is tested.
- No speculative abstractions, no extra services (Redis/queues are optional — justify before adding). Keep it simple, but layered so it can grow (HTTP → service/action → DB).
- Record anything deliberately left out in README "Known limitations".

## Stack / layout
- `apps/api` — Laravel 13, PHP 8.5, FrankenPHP (:8000). `apps/web` — Next.js 16 (App Router, TS), Node 26 (:3000). `db` — PostgreSQL 18.
- `docker-compose.yml` = prod-like base; `compose.override.yml` = dev (bind mounts, `dev` targets, debuggers). Auto-merged by `docker compose`.
- `docs/` — requirements, HLD/design notes. `docs/ai/` — prompt log + token usage (assessment deliverable).

## Commands (run via Docker; no host PHP)
- Up: `docker compose up -d --build` · Migrate: `docker compose exec api php artisan migrate`
- API checks: `docker compose exec api composer lint|analyse|deptrac|test` (Pint, Larastan max, module boundaries, tests)
- Web checks: `docker compose exec web npm run lint|typecheck|test`
- Shared FE/BE contracts: `npm run contracts` after changing a response shape (`contracts/schemas/*.schema.json`, the single source) or any `Contracts`/`Shared` enum. It regenerates TS enums, the enum schema, both apps' schema copies and the web TS types; CI fails on drift.
- Seed: `docker compose exec api php artisan db:seed` (idempotent)
- Token/prompt report: `node scripts/ai-usage.mjs` → `docs/ai/token-usage.md`

## TDD
- Red → green → refactor: write the failing test first, then the minimum code to pass.
- API: PHPUnit 12 (no Pest), Feature tests hit real Postgres `app_test` (`RefreshDatabase`); assertions via `self::assert*`. Quota/concurrency rules need tests that prove the invariant.
- Web: Vitest + Testing Library (`npm run test:watch`), `*.test.ts(x)` next to the code.
- One-shot gates: `composer check` (api), `npm run check` (web).

## Modules (API)
- `app/Modules/{Shared,Identity,Providers,Quota,Lookups}`; layout and rules in `docs/hld.md` §2.
- Import only your own module, `Shared`, and other modules' `Contracts`. Reference other modules by ID; no cross-module Eloquent relations. `composer deptrac` must pass.
- Services and repositories are interfaces bound in the module's ServiceProvider; Eloquent only in models and `Infrastructure`.

## Rules
- Comments: precise and minimal — a one-line "why", never a restatement of the code.
- Correctness of quota under concurrency lives in the **database** (transactions, row locks / conditional updates, unique constraints) — never in app memory.
- PHP: `declare(strict_types=1)`, typed everything, Larastan max must pass. TS: strict config, no `any`, lint + typecheck must pass.
- Containers stay non-root, read-only rootfs, pinned image tags. Never commit `.env` or secrets.
- Small, focused commits (conventional style). Do not commit without being asked.
- Verify (run the checks) before claiming something works.
