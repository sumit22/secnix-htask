import { describe, expect, it } from "vitest";
import { clockOffset, formatMmSs, remainingSeconds } from "./countdown";

describe("countdown", () => {
  it("computes clock offset as server minus client", () => {
    const client = Date.parse("2026-01-01T00:00:00Z");
    expect(clockOffset("2026-01-01T00:00:10Z", client)).toBe(10_000);
  });

  it("uses the server-adjusted clock", () => {
    const expires = "2026-01-01T00:05:00Z";
    const now = Date.parse("2026-01-01T00:00:00Z");
    expect(remainingSeconds(expires, 0, now)).toBe(300);
    // client is 60s behind the server, so less time remains
    expect(remainingSeconds(expires, 60_000, now)).toBe(240);
  });

  it("rounds up partial seconds and clamps at zero", () => {
    const expires = "2026-01-01T00:00:10Z";
    const now = Date.parse("2026-01-01T00:00:09.500Z");
    expect(remainingSeconds(expires, 0, now)).toBe(1);
    expect(remainingSeconds(expires, 0, now + 5000)).toBe(0);
  });

  it("formats mm:ss", () => {
    expect(formatMmSs(0)).toBe("00:00");
    expect(formatMmSs(65)).toBe("01:05");
    expect(formatMmSs(300)).toBe("05:00");
  });
});
