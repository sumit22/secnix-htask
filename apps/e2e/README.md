# E2E tests (Playwright)

The suite needs short leases and a tiny quota, so the stack must run with `compose.e2e.yml`:

```sh
# from repo root
docker compose -f docker-compose.yml -f compose.override.yml -f compose.e2e.yml up -d
cd apps/e2e
npm install && npx playwright install chromium
npm test            # or: npm run test:headed
npx playwright show-report
```

`globalSetup` runs `migrate:fresh --seed` (inside the `api` container) before the suite; each spec
also resets state in `beforeEach`. Tests assume TTL = 8s and daily quota = 2.

Restore the normal stack afterwards:

```sh
docker compose up -d
docker compose exec -T api php artisan migrate:fresh --seed --force
```
