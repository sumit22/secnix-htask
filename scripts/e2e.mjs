#!/usr/bin/env node
// Runs the Playwright suite against the docker stack, then restores the normal stack.
//   npm run test:e2e            headless
//   npm run test:e2e:review     visible browser, slowed down (SLOWMO=800 to slow further)
// The suite needs an 8 s lease and a quota of 2 (compose.e2e.yml), so the API processes are
// restarted with those settings for the run and back to the defaults afterwards (data is reseeded).
import { execSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const e2e = join(root, 'apps/e2e');
const review = process.argv.includes('--review');
const sh = (cmd, cwd = root) => {
  console.log(`\n$ ${cmd}`);
  execSync(cmd, { cwd, stdio: 'inherit' });
};

async function waitFor(url, timeoutMs = 180_000) {
  const until = Date.now() + timeoutMs;
  while (Date.now() < until) {
    try {
      if ((await fetch(url)).ok) return;
    } catch {
      /* not up yet */
    }
    await new Promise((r) => setTimeout(r, 2000));
  }
  throw new Error(`${url} not healthy`);
}

if (!existsSync(join(e2e, 'node_modules'))) sh('npm ci --no-fund --no-audit', e2e);
sh('npx playwright install chromium', e2e);

const e2eStack = 'docker compose -f docker-compose.yml -f compose.override.yml -f compose.e2e.yml';
let failed = false;
try {
  sh(`${e2eStack} up -d`);
  await waitFor('http://localhost:8000/up');
  await waitFor('http://localhost:3000/api/health');
  sh(review ? 'npx playwright test -c playwright.review.config.ts' : 'npx playwright test', e2e);
} catch {
  failed = true;
} finally {
  console.log('\nRestoring the normal stack (default lease and quota, fresh seed)…');
  sh('docker compose up -d');
  await waitFor('http://localhost:8000/up');
  sh('docker compose exec -T api php artisan migrate:fresh --seed --force');
}
console.log(failed ? '\nE2E FAILED — report: cd apps/e2e && npx playwright show-report' : '\nE2E passed — report: cd apps/e2e && npx playwright show-report');
process.exit(failed ? 1 : 0);
