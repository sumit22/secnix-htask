import { describe, expect, it } from "vitest";
import { sampleLookup, sampleQuota } from "../lib/fixtures";
import { isApiErrorEnvelope, isLookupView, isQuotaView } from "./validate";

const leased = {
  status: "leased",
  queue: null,
  lease: {
    leased_at: "2026-01-01T00:00:00Z",
    expires_at: "2026-01-01T00:05:00Z",
  },
} as const;

describe("runtime contract validation (contracts/schemas)", () => {
  it("accepts contract-conformant payloads", () => {
    expect(isLookupView(sampleLookup())).toBe(true);
    expect(isLookupView(sampleLookup(leased))).toBe(true);
    expect(isQuotaView(sampleQuota())).toBe(true);
    expect(
      isApiErrorEnvelope({ error: { code: "lease_expired", message: "x" } }),
    ).toBe(true);
  });

  it.each([
    ["unknown status", { ...sampleLookup(), status: "running" }],
    ["extra field", { ...sampleLookup(), surprise: 1 }],
    ["missing field", { ...sampleLookup(), server_time: undefined }],
    ["non-uuid id", { ...sampleLookup(), id: "l1" }],
    ["bad timestamp", { ...sampleLookup(), created_at: "yesterday" }],
    ["leased without a lease", { ...sampleLookup(leased), lease: null }],
    ["queued without a position", { ...sampleLookup(), queue: null }],
    ["lease on a queued lookup", { ...sampleLookup(), lease: leased.lease }],
    [
      "completed without a result",
      {
        ...sampleLookup(),
        status: "completed",
        queue: null,
        completed_at: "2026-01-01T00:01:00Z",
      },
    ],
  ])("rejects a lookup with %s", (_, payload) => {
    expect(isLookupView(payload)).toBe(false);
  });

  it("rejects negative quota counters and unknown error codes", () => {
    expect(isQuotaView({ ...sampleQuota(), remaining: -1 })).toBe(false);
    expect(
      isApiErrorEnvelope({ error: { code: "teapot", message: "x" } }),
    ).toBe(false);
  });
});
