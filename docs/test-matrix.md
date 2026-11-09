# Test matrix — requirement → execution path → test

Layers: **U** unit · **I** integration (HTTP + real Postgres, `apps/api/tests/Feature`) · **C** concurrency (forked processes) · **L** HTTP load (`scripts/load-test.mjs`) · **E** browser E2E (`apps/e2e`).

## Request enrichment — `POST /lookups` (RequestEnrichmentService)

| # | Branch | Data flow / outcome | Tests |
|---|---|---|---|
| R1 | Unit free, queue empty | quota lock → reserve → `leased` + TTL → idempotency row → 201 + Location | I `test_reserves_a_lease_when_quota_is_available` · E |
| R2 | Quota exhausted | `queued` + queue row (identity = FIFO) → 201, position N | I `test_queues_in_arrival_order_when_quota_is_exhausted` · C · E |
| R3 | Unit free but queue non-empty | newcomer queued behind waiters (no queue jumping) | I `test_new_request_cannot_jump_the_queue_when_a_unit_frees_up` |
| R4 | Same key + same payload | replay original lookup, 200 + `Idempotent-Replayed`, no unit | I `test_retry_with_same_key_replays_without_spending_a_unit` · C `test_concurrent_retries_with_one_key_are_idempotent` · L |
| R5 | Same key + different payload | 422 `idempotency_key_reused` | I `test_reusing_a_key_for_a_different_request_is_rejected` |
| R6 | Indicator already active | existing lookup, `deduplicated: true`, key recorded, no unit | I `test_active_indicator_is_shared_without_spending_a_unit` · C `test_same_indicator_from_many_clients_…` · L · E |
| R7 | Retry of a deduplicated key after completion | still the same lookup, no unit | I `test_retry_of_a_deduplicated_request_after_completion_…` |
| R8 | Indicator finished earlier | new lookup allowed | I `test_finished_indicator_can_be_requested_again` |
| R9 | Missing / oversized key | 400 `idempotency_key_missing` | I `test_missing_idempotency_key_is_rejected`, `test_overlong_idempotency_key_is_rejected` |
| R10 | Unknown / malformed analyst | 422 `analyst_not_found` | I `test_unknown_or_missing_analyst_is_rejected` |
| R11 | Invalid body / indicator / type mismatch | 422 `validation_failed` + `fields`, nothing written | I `test_invalid_input_is_rejected` (5 cases) · U `IndicatorNormalizerTest` · E |
| R12 | Unknown provider | 422 `validation_failed` | I `test_unknown_provider_is_rejected` |
| R13 | ~200 simultaneous distinct requests | exactly `quota` leased, rest queued, 0 errors | C `test_never_leases_more_than_the_quota_…` (60 procs) · L (200 HTTP) |

## Run lookup — `POST /lookups/{id}/run` (RunLookupService)

| # | Branch | Outcome | Tests |
|---|---|---|---|
| X1 | Leased, unexpired | provider call under row lock → consume → `completed` + result | I `test_run_consumes_the_unit_and_returns_a_verdict` · E |
| X2 | Already completed | same stored result, no second unit | I `test_running_twice_…` · C `test_concurrent_runs_consume_one_unit` |
| X3 | Teammate runs shared lease | allowed | I `test_teammate_can_run_a_shared_lease` |
| X4 | Other team | 403 `forbidden` | I `test_other_team_cannot_run` |
| X5 | Queued | 409 `lease_not_active` | I `test_queued_lookup_cannot_run` |
| X6 | Lease overdue at run time | expire (commits) → release → promote head → 409 `lease_expired` | I `test_run_after_expiry_fails_returns_the_unit_and_promotes_the_queue` · E |
| X7 | Provider failure (domain error or any unexpected exception) | rollback, lease kept, 503 `provider_unavailable` + Retry-After | I `test_provider_failure_keeps_the_lease_for_a_retry`, `test_unexpected_provider_exception_is_provider_unavailable` |
| X9 | Run in flight + concurrent dedup request | no deadlock (row locks `FOR NO KEY UPDATE`) | C `test_run_in_flight_does_not_deadlock_with_a_deduplicated_request` |
| X8 | Unknown / malformed id | 404 `not_found` | I `test_unknown_lookup_is_not_found` |

## Expiry & promotion (LeaseManager, ExpireLeasesService, lazy reads)

| # | Branch | Outcome | Tests |
|---|---|---|---|
| P1 | Sweeper: overdue leases | `expired`, units released, FIFO promotion of N heads | I `test_sweeper_expires_overdue_leases_and_promotes_fifo` · E |
| P2 | Read of an overdue lease (no sweeper) | lazily expired before responding | I `test_status_read_expires_lazily_without_the_sweeper` · E |
| P3 | Illegal transition | `LogicException` (state machine guard) | U `LookupStatusTest` |

## Reads, schema, plumbing

| Area | Tests |
|---|---|
| Dashboard numbers (`GET /quota`) | I `test_quota_dashboard` · E |
| Listing + filters (`GET /lookups`) | I `test_recent_lists_newest_first_with_filters` · E (survives reload) |
| Malformed query params → 422; framework errors (405) → envelope | I `test_malformed_query_params_are_validation_errors`, `test_http_errors_use_the_error_envelope` |
| Leases never outlive the UTC quota day | I `test_leases_never_outlive_their_quota_day` |
| Analysts (`GET /analysts`) | I `RequesterDirectoryTest` · E |
| DB invariants (CHECK / UNIQUE / partial unique) | I `SchemaConstraintsTest` (13) |
| Quota ledger counters / CHECK backstop | I `QuotaLedgerTest`, `QuotaAllocationRepositoryTest` |
| FE/BE contract (shapes + invariants) | I every `LookupApiTest`/`RequesterDirectoryTest` response validated (`AssertsContract`) · U `ContractSchemasTest` (drift rejected) · Vitest `validate.test.ts` · CI `contracts` job |
| Contracts drift (PHP enums → TS/schema) | I `ContractsExportTest` · CI diff check |
| Module boundaries | Deptrac (pre-commit + CI) |
| Web units (countdown, API client, cards, form) | Vitest (`apps/web/src/**/*.test.ts(x)`) |
