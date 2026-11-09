# Enrichment Lookup Coordinator

Shares a scarce threat-intel provider quota (10 lookups/day) between many analysts and automated playbooks: **leases** a unit for 5 minutes, **queues** requests FIFO when none is free, **deduplicates** indicators, makes retries **idempotent**, and **never exceeds the quota** — proven with ~200 concurrent requests.

## For reviewers: start here

### 1. Run it (≈ 5 min, first build included)

Prerequisites: **Docker** (Desktop or Engine with Compose v2), **Node.js ≥ 22**, **git**. Free ports: 3000, 8000, 54329.

```bash
git clone <this repo> && cd secnix-htask
npm run setup          # .env + app key, build, deps, start, migrate, seed — re-runnable
```

Open **http://localhost:3000**, pick an analyst, request an indicator (e.g. `8.8.8.8`, `evil.example.com`, a SHA-256), watch the lease countdown, press **Run lookup**. Request 11+ distinct indicators to see queueing; open a second browser profile as another analyst and request the same indicator to see dedup.

### 2. Run the tests

| What | Command | Notes |
|---|---|---|
| API: Pint, Larastan max, Deptrac, PHPUnit (unit, integration on real Postgres, forked-process concurrency) | `npm run test:api` | ~1 min |
| Web: ESLint, tsc, Prettier, Vitest | `npm run test:web` | |
| Browser E2E, every UI flow (Playwright) | `npm run test:e2e` | switches the stack to an 8 s lease / quota 2, then restores it |
| Same, **visible browser in slow motion** | `npm run test:e2e:review` | `SLOWMO=800 npm run test:e2e:review` to slow further |
| ~200 simultaneous HTTP requests + invariant check | `npm run test:load` | reseeds the DB first |

Stop everything: `npm run down` (add `-- -v` to drop the data volumes).

### 3. Where to look (suggested 15-minute tour)

| Question | Read |
|---|---|
| What was asked? | [docs/requirements.md](docs/requirements.md) (source of truth) |
| How is it designed and why? | [docs/hld.md](docs/hld.md) — schema, state machine, concurrency, queues, modules |
| What does the API promise? | [docs/api.md](docs/api.md) + machine-readable [contracts/schemas](contracts/schemas) |
| Which test proves which rule? | [docs/test-matrix.md](docs/test-matrix.md) — requirement → branch → test |
| The core logic | [Lookups/Application](apps/api/app/Modules/Lookups/Application) — `RequestEnrichmentService`, `RunLookupService`, `LeaseManager` |
| The DB guarantees | [migrations](apps/api/database/migrations) (CHECK / UNIQUE / partial unique index) |
| Concurrency proof | [ConcurrencyTest.php](apps/api/tests/Feature/Modules/Lookups/ConcurrencyTest.php), [scripts/load-test.mjs](scripts/load-test.mjs) |
| How AI was used | [AI usage](#ai-usage) below, [docs/ai/](docs/ai) (prompts, chat, tokens) |

## Tech stack

| Layer | Choice |
|---|---|
| API | Laravel 13 · PHP 8.5 · FrankenPHP · PHPUnit 12 · Larastan (max + strict rules) · Pint · Deptrac |
| Web | Next.js 16 (App Router) · React 19 · TypeScript (strict+) · Vitest · Testing Library · Playwright |
| Data | PostgreSQL 18 (all quota correctness lives here) |
| Runtime | Docker Compose: `db`, `api`, `worker`, `scheduler`, `web`; hardened, non-root, read-only images |
| Tooling | lefthook (gitleaks, lint, types, Deptrac, commitlint), GitHub Actions CI |

## Architecture

```
Next.js dashboard ──HTTP/JSON (X-Analyst-Id, Idempotency-Key)──▶ Laravel API ──▶ PostgreSQL
                                                         worker  (queue:work)  ──┤
                                                         scheduler (sweep / 10s) ┘
```

The API is split into modules with enforced boundaries (`apps/api/app/Modules`): **Shared** (clock, transactions, indicator value object), **Identity** (users/analysts), **Providers** (catalog + mock provider), **Quota** (ledger), **Lookups** (request / run / expire / query). Modules talk through `Contracts` (interfaces + DTOs) and reference each other by ID only; services depend on repository interfaces (SOLID). Deptrac fails the build on any boundary violation.

### How the quota is protected under concurrency

1. **One serialisation point per provider.** Every reservation decision runs in a transaction that first row-locks that day's quota row (`SELECT … FOR NO KEY UPDATE`). Idempotency lookup, indicator dedup and lease-vs-queue are all decided *after* the lock, so concurrent requests cannot interleave.
2. **The database refuses to overspend.** `CHECK (reserved + consumed <= quota_limit)` and non-negative counters; counters change only by atomic `UPDATE … SET reserved = reserved + 1`. A bug in application code cannot exceed the quota.
3. **Uniqueness in the schema.** Partial unique index: one `queued|leased` lookup per (provider, type, normalised value). `UNIQUE (requested_by, idempotency_key)` in a separate `idempotency_keys` table — every request records its key, **including deduplicated ones**, so a retry after the shared lookup finishes still maps to it and never spends a unit.
4. **Run holds only its own row lock** across the provider call: concurrent runs of the same lookup wait and then return the stored result; the expiry sweep uses `SKIP LOCKED`, so a lease is never expired (and its unit re-issued) while its lookup is in flight.
5. **No deadlocks.** Row locks are `FOR NO KEY UPDATE`, which does not conflict with the `FOR KEY SHARE` that foreign-key checks take; with the sweeper skipping locked rows, a run in flight and a concurrent dedup request cannot deadlock (regression-tested with a slow provider).
6. **Day boundary.** A lease never outlives its quota day (`expires_at ≤ period_end`), so leases from before UTC midnight cannot overlap the next day's fresh allocation.

### FE/BE data integrity

One machine-readable contract, enforced at both ends ([contracts/schemas](contracts/schemas)):

- **Response shapes** are JSON Schemas (with invariants: `lease` only when `leased`, `queue` only when `queued`, `result` only when `completed`, score 0–100, UUIDs, ISO timestamps).
- **Enum values** (statuses, verdicts, error codes) are generated from the PHP enums, so they cannot diverge.
- **API:** every integration-test response is validated against the schemas, so the backend cannot ship a shape the contract does not allow.
- **Web:** TypeScript types are generated from the schemas, and every response is validated at runtime (ajv). A drifting API fails loudly instead of corrupting UI state.
- **Lease time** is never hard-coded in the UI: the countdown uses the server's `lease.expires_at` corrected by `server_time`, and the TTL itself comes from `GET /quota`.
- `npm run contracts` regenerates everything; a CI job fails on any drift.

### Lease expiry and promotion

- Lease = `status = leased` + `lease_expires_at` (TTL 300 s, configurable).
- Expiry happens in three places, all through the same `LeaseManager` code path: the **scheduler** sweep every 10 s (queued job, idempotent, safe on N workers), **lazily on every read/request** (so correctness never depends on the worker), and on **run** of an overdue lease.
- Expiring returns the unit and immediately promotes the oldest waiting requests (`lookup_queue` bigint identity = arrival order; UUIDv7 is not monotonic across connections). A new request never jumps a non-empty queue.

State machine: `queued → leased → completed | expired` (enforced in `LookupStatus` and by guarded updates).

## Manual commands (what `npm run setup` / `test:*` do)

```bash
cp .env.example .env                                     # then set APP_KEY (npm run setup generates one)
docker compose build
docker compose run --rm --no-deps api composer install   # dev containers bind-mount the source, so vendor/ lives there
docker compose up -d
docker compose exec api php artisan migrate --seed
docker compose exec api composer check                   # API gate
docker compose exec web npm run check                    # web gate
```

Services: `db` (Postgres 18, host port 54329), `api` (:8000, health `/up`), `worker` (queue), `scheduler` (expiry sweep), `web` (:3000). Optional: `npm install` at the root installs the git hooks (lefthook).

## API

Base `/api/v1`, full contract in [docs/api.md](docs/api.md).

| Method & path | Purpose |
|---|---|
| `POST /lookups` | Request enrichment → `201 leased` / `201 queued` / `200` replay or dedup |
| `POST /lookups/{id}/run` | Call provider, consume the unit (idempotent) |
| `GET /lookups/{id}` | Status (lease countdown, queue position, result) |
| `GET /lookups?requested_by=&status=` | Recent lookups (max 50) |
| `GET /quota` | Dashboard numbers |
| `GET /analysts` | Analyst picker |

Errors: `{ "error": { "code", "message", "fields"? } }` with stable codes shared with the web app (`apps/web/src/contracts/generated.ts`, generated from PHP enums; CI fails on drift).

## Deployment

The stack is 12-factor: one image per app, config via environment, logs to stderr, stateless processes.

1. Build: `docker compose -f docker-compose.yml build` (prod targets, no dev overrides).
2. Configure environment (e.g. `.env` or your orchestrator's secrets):

   | Variable | Required | Default | Purpose |
   |---|---|---|---|
   | `APP_KEY` | yes | — | Laravel encryption key (`php artisan key:generate --show`) |
   | `DB_PASSWORD` | yes | — | Postgres password |
   | `DB_DATABASE`, `DB_USERNAME` | no | `app` | Postgres database/user |
   | `APP_URL` | no | `http://localhost:8000` | Public API URL (used in `Location`) |
   | `NEXT_PUBLIC_API_URL` | build arg | `http://localhost:8000` | API URL baked into the web bundle |
   | `CORS_ALLOWED_ORIGINS` | no | `http://localhost:3000` | Comma-separated web origins |
   | `ENRICHMENT_DEFAULT_DAILY_QUOTA` | no | `10` | Units per provider per UTC day |
   | `ENRICHMENT_LEASE_TTL_SECONDS` | no | `300` | Lease length |
   | `QUEUE_CONNECTION` | no | `database` | `sqs` / RabbitMQ driver later — config only |

3. Start: `docker compose -f docker-compose.yml up -d` (services `db`, `api`, `worker`, `scheduler`, `web`; run exactly one `scheduler`).
4. Migrate and seed (one-off admin process, idempotent): `docker compose -f docker-compose.yml exec api php artisan migrate --force --seed`.

## Assumptions (security & domain)

- **No authentication** (per brief): the caller is identified by `X-Analyst-Id`. Anyone who can reach the API can act as any analyst; deploy behind a trusted network or add Laravel auth (the header middleware is the single swap point).
- **Team scope**: a shared (deduplicated) lease can be run by any member of the requesting team.
- **Quota** is org-level per provider per **UTC day**; a new day is a new allocation row (no destructive reset).
- **Indicators** are normalised before dedup: lowercase, trailing dot stripped, canonical IP (IPv6 compressed); hashes accepted as MD5/SHA-1/SHA-256 hex.
- A failed provider call is not a state: the lease is kept so the analyst can retry within the TTL.
- The mock provider returns a random verdict; real providers plug in behind `ThreatIntelProvider`.

## Known limitations / left out

- Authentication/authorisation beyond the team check on run; reads (`GET /lookups*`) are not team-scoped.
- Team-level quota caps (schema supports `team_id` allocations; not enforced — would require per-team FIFO).
- Per-provider throughput is serialised on one row lock (correct, ~8 s for 200 requests in dev on Windows bind mounts); fine for a 10/day quota, would need sharding of the lock for high-volume providers.
- Queued requests carry over to the next UTC day and are promoted from its allocation; the queue has no per-requester cap or TTL (a noisy playbook could fill it).
- Indicator normalisation does not yet fold IPv4-mapped IPv6 (`::ffff:a.b.c.d`) or convert Unicode domains to punycode; private/loopback IPs are accepted.
- Malformed list/quota query params (e.g. `?requested_by=abc`) and framework errors such as 405 are not yet mapped to the error envelope; an unexpected (non-domain) provider exception returns 500 instead of 503. Fixes are written but not merged (independent audit items 3, 5, 10).
- The dashboard poll takes the quota row lock briefly every 3 s (correct, but contends with writes under heavy load).
- No push updates (the UI polls); no idempotency-key TTL/purge job; no rate limiting.

## What I would improve with more time

Authentication (Sanctum) and team-scoped reads · rate limiting per analyst · SSE for queue/lease updates · team caps with per-team FIFO · outbox + SQS/RabbitMQ for provider calls by playbooks · idempotency-key retention policy · OpenAPI spec generating the TS client · observability (structured logs, metrics on queue depth and lease expiries).

## AI usage

- Built with Claude Code (Opus 5.5 main session; Sonnet subagents for scaffolding, the web UI and Playwright tests; an independent reviewer agent for the audit).
- Prompts: [docs/ai/prompts.md](docs/ai/prompts.md) · full chat: [docs/ai/chat-history.md](docs/ai/chat-history.md) · tokens: [docs/ai/token-usage.md](docs/ai/token-usage.md) — exported automatically by a Stop hook (`scripts/ai-usage.mjs`).
- Build time: first prompt 2026-11-09 09:54 UTC; see `docs/ai/token-usage.md` for the end time and totals.

### Where the AI was wrong and what changed

- A scaffolding agent installed **Sanctum** (auth) although the brief says no auth — removed.
- PHPUnit's `<env>` did not override Docker's env vars, so **tests ran against the dev database** and `RefreshDatabase` would have wiped it — caught by a test asserting the DB name; fixed with `<server force>`.
- Idempotency keys first lived on `lookup_requests`; designing the API exposed that **a retry of a deduplicated request after completion would spend a second unit** — moved to an `idempotency_keys` table.
- A Docker volume hid `vendor/`, `cap_drop: ALL` broke root writes to a tmpfs, and a bootstrap rewrite dropped the default middleware groups — each caught by running the stack, fixed.
- `jsonb` reorders keys, so the first `run` response differed from later reads — now returns the stored row.
- Concurrency tests commit real rows (forked processes) and leaked into transactional tests — added truncation on teardown.
- An **independent AI audit** (a separate agent given only the requirements and code) found two real gaps that all tests had missed: a **deadlock** between a run in flight and a concurrent dedup request (FK `KEY SHARE` vs `FOR UPDATE`; the run could be rolled back after the provider was called), and **leases straddling UTC midnight** (allowing up to 2× the daily limit). Both were reproduced with failing tests first, then fixed (`FOR NO KEY UPDATE`; leases capped at the period end).
