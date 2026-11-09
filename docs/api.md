# API contract (v1)

Base: `/api/v1`. JSON only. Shared enums/codes: `apps/web/src/contracts/generated.ts`.

## Conventions
- **Requester:** `X-Analyst-Id: <user uuid>` on every call (no auth yet). Unknown/missing → `422 analyst_not_found`.
- **Success:** `{ "data": … }`. **Error:** `{ "error": { "code": ApiErrorCode, "message": string, "fields"?: { [name]: string[] } } }`.
- All timestamps ISO-8601 UTC. Responses include `server_time` so clients compute countdowns without clock skew.
- Lazy expiry: any read applies an overdue lease expiry before responding.

## Endpoints

| Method & path | Purpose |
|---|---|
| `POST /lookups` | Request enrichment → lease or queue |
| `POST /lookups/{id}/run` | Call the provider, consume the reserved unit |
| `GET /lookups/{id}` | Status of one lookup |
| `GET /lookups?requested_by=&status=` | Recent lookups, newest first, max 50 |
| `GET /quota?provider=` | Dashboard numbers |
| `GET /analysts` | Analyst picker |

### `POST /lookups`
Headers: `Idempotency-Key` (required, ≤100 chars), `X-Analyst-Id`.
Body: `{ "indicator": string, "indicator_type"?: "ip"|"domain"|"hash", "provider"?: string }`

| Case | Status | Body |
|---|---|---|
| Unit available | `201` + `Location` | `LookupView` (`leased`) |
| Quota exhausted | `201` + `Location` | `LookupView` (`queued`, with position) |
| Retry: same key + same payload | `200` + `Idempotent-Replayed: true` | current `LookupView` of the original lookup |
| Same key, different payload | `422 idempotency_key_reused` | |
| Indicator already active (any requester) | `200` | existing `LookupView`, `deduplicated: true` |
| Missing key | `400 idempotency_key_missing` | |
| Invalid input / unknown provider | `422 validation_failed` | |

Every request records its idempotency key (table `idempotency_keys`), including deduplicated ones, so a retry always returns the same lookup and never spends a unit.

### `POST /lookups/{id}/run`
Naturally idempotent (no key needed). Allowed for any requester in the lookup's team.

| Case | Status |
|---|---|
| Leased and unexpired | `200` `LookupView` (`completed`, `result`) |
| Already completed | `200` same result (no second unit) |
| Lease expired | `409 lease_expired` |
| Still queued | `409 lease_not_active` |
| Provider failure | `503 provider_unavailable` + `Retry-After` (lease kept) |
| Other team | `403 forbidden` |
| Unknown id | `404 not_found` |

### `LookupView`
```jsonc
{
  "id": "uuid", "status": "queued|leased|completed|expired",
  "indicator": { "type": "domain", "value": "evil.example.com" },
  "provider": "mock-intel", "requested_by": "uuid", "deduplicated": false,
  "lease": { "leased_at": "…", "expires_at": "…" } | null,
  "queue": { "position": 3 } | null,            // 1 = next in line
  "result": { "verdict": "malicious|suspicious|harmless|unknown", "score": 0-100,
              "malware_families": ["…"], "looked_up_at": "…" } | null,
  "created_at": "…", "completed_at": "…" | null, "expired_at": "…" | null,
  "server_time": "…"
}
```

### `GET /quota?provider=mock-intel`
```jsonc
{ "provider": "mock-intel", "period": { "start": "…", "end": "…" },
  "limit": 10, "reserved": 2, "consumed": 5, "remaining": 3, "queued": 4,
  "lease_ttl_seconds": 300, "server_time": "…" }
```

### `GET /analysts`
`[{ "id": "uuid", "name": "Alice (Tier 1)", "team": { "id": "uuid", "slug": "default" } }]`

## Error codes
`validation_failed` 422 · `analyst_not_found` 422 · `idempotency_key_missing` 400 · `idempotency_key_reused` 422 · `not_found` 404 · `forbidden` 403 · `lease_expired` 409 · `lease_not_active` 409 · `provider_unavailable` 503
