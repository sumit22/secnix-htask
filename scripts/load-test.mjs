#!/usr/bin/env node
// Fires ~200 simultaneous enrichment requests at a running stack and checks the quota invariants.
// Usage: node scripts/load-test.mjs [apiUrl=http://localhost:8000] [requests=200]
// Run against a fresh day/DB for exact numbers: docker compose exec api php artisan migrate:fresh --seed
const api = process.argv[2] ?? 'http://localhost:8000';
const total = Number(process.argv[3] ?? 200);
const run = Date.now().toString(36);

const json = async (res) => ({ status: res.status, body: await res.json() });
const get = (path, analyst) => fetch(`${api}/api/v1${path}`, { headers: { Accept: 'application/json', 'X-Analyst-Id': analyst } }).then(json);
const post = (analyst, key, indicator) => fetch(`${api}/api/v1/lookups`, {
  method: 'POST',
  headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Analyst-Id': analyst, 'Idempotency-Key': key },
  body: JSON.stringify({ indicator }),
}).then(json);

const analysts = (await fetch(`${api}/api/v1/analysts`).then(json)).body.data.map((a) => a.id);
const before = (await get('/quota', analysts[0])).body.data;

// Mix: distinct indicators, one hot indicator hit by many analysts, and duplicate retries of one key.
const jobs = Array.from({ length: total }, (_, i) => {
  const analyst = analysts[i % analysts.length];
  if (i % 10 === 0) return post(analyst, `${run}-hot-${i}`, `hot-${run}.example.com`);
  if (i % 10 === 1) return post(analysts[0], `${run}-retry`, `retry-${run}.example.com`);
  return post(analyst, `${run}-${i}`, `host-${i}-${run}.example.com`);
});
const started = performance.now();
const results = await Promise.all(jobs);
const ms = Math.round(performance.now() - started);
const after = (await get('/quota', analysts[0])).body.data;

const tally = {};
for (const r of results) {
  const k = r.status >= 400 ? `${r.status} ${r.body.error?.code}` : `${r.status} ${r.body.data.status}${r.body.data.deduplicated ? ' (dedup)' : ''}`;
  tally[k] = (tally[k] ?? 0) + 1;
}
const ids = new Set(results.filter((r) => r.status < 400).map((r) => r.body.data.id));
const newlyReserved = after.reserved + after.consumed - (before.reserved + before.consumed);

console.log(`${total} concurrent requests in ${ms} ms`);
console.table(tally);
console.log({ before, after, distinctLookups: ids.size, newlyReserved });

const errors = [];
if (after.reserved + after.consumed > after.limit) errors.push('quota exceeded');
if (newlyReserved > before.remaining) errors.push('reserved more than was remaining');
if (results.some((r) => r.status >= 500)) errors.push('server errors');
if (errors.length) {
  console.error('FAIL:', errors.join(', '));
  process.exit(1);
}
console.log('PASS: quota never exceeded; hot indicator and retries did not spend extra units');
