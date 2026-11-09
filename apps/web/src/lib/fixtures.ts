import type { LookupView, QuotaView } from "../contracts/api";

// Test helpers only.
export function sampleLookup(over: Partial<LookupView> = {}): LookupView {
  return {
    id: "01a1c02f-0000-7000-8000-000000000001",
    status: "queued",
    indicator: { type: "domain", value: "evil.example.com" },
    provider: "mock-intel",
    requested_by: "01a1c02f-0000-7000-8000-0000000000a1",
    deduplicated: false,
    lease: null,
    queue: { position: 3 },
    result: null,
    created_at: "2026-01-01T00:00:00Z",
    completed_at: null,
    expired_at: null,
    server_time: "2026-01-01T00:00:00Z",
    ...over,
  };
}

export function sampleQuota(over: Partial<QuotaView> = {}): QuotaView {
  return {
    provider: "mock-intel",
    period: { start: "2026-01-01T00:00:00Z", end: "2026-01-02T00:00:00Z" },
    limit: 10,
    reserved: 2,
    consumed: 5,
    remaining: 3,
    queued: 4,
    lease_ttl_seconds: 300,
    server_time: "2026-01-01T00:00:00Z",
    ...over,
  };
}
