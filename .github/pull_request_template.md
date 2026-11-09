## Summary
<!-- What and why, in 1–3 lines. -->

## Requirement
<!-- Which part of docs/requirements.md this serves (e.g. "Business rules → idempotency key"). -->

## Type
- [ ] feat  - [ ] fix  - [ ] refactor  - [ ] chore/ci  - [ ] docs

## Changes
-

## How tested
<!-- Commands run, tests added, manual steps. For quota logic: concurrency test results. -->

## Checklist
- [ ] Scope stays within core requirements (no speculative features)
- [ ] Quota invariant safe under concurrency (DB-enforced: tx/locks/constraints)
- [ ] API: `composer lint`, `composer analyse`, `composer test` pass
- [ ] Web: `npm run lint`, `typecheck`, `test` pass
- [ ] Migrations included and reversible (if schema changed)
- [ ] README / known limitations updated (if behaviour changed)
- [ ] No secrets or `.env` committed

## Out of scope / follow-ups
-
