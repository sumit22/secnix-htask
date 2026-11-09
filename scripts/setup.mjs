#!/usr/bin/env node
// One-command local setup for reviewers: npm run setup
// Creates .env (with a fresh APP_KEY), builds the images, installs PHP deps into the bind-mounted
// source, starts the stack, waits for health, then migrates and seeds. Safe to re-run.
import { execSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { copyFileSync, existsSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const sh = (cmd) => {
  console.log(`\n$ ${cmd}`);
  execSync(cmd, { cwd: root, stdio: 'inherit' });
};

async function waitFor(url, label, timeoutMs = 300_000) {
  const until = Date.now() + timeoutMs;
  process.stdout.write(`Waiting for ${label} (${url})`);
  while (Date.now() < until) {
    try {
      if ((await fetch(url)).ok) return console.log(' ✓');
    } catch {
      /* not up yet */
    }
    process.stdout.write('.');
    await new Promise((r) => setTimeout(r, 2000));
  }
  throw new Error(`${label} did not become healthy within ${timeoutMs / 1000}s`);
}

const envFile = join(root, '.env');
if (!existsSync(envFile)) {
  copyFileSync(join(root, '.env.example'), envFile);
  console.log('Created .env from .env.example');
}
const env = readFileSync(envFile, 'utf8');
if (/^APP_KEY=\s*$/m.test(env)) {
  writeFileSync(envFile, env.replace(/^APP_KEY=\s*$/m, `APP_KEY=base64:${randomBytes(32).toString('base64')}`));
  console.log('Generated APP_KEY in .env');
}

sh('docker compose build');
sh('docker compose run --rm --no-deps api composer install --no-interaction --no-progress');
sh('docker compose up -d');
await waitFor('http://localhost:8000/up', 'API');
sh('docker compose exec -T api php artisan migrate --seed --force');
await waitFor('http://localhost:3000/api/health', 'web app (first compile can take a minute)');

console.log(`
Ready.
  Dashboard  http://localhost:3000
  API        http://localhost:8000/api/v1/analysts
Next: npm run test:api · npm run test:web · npm run test:e2e   (see README → Run the tests)`);
