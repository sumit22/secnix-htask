import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  ApiError,
  createLookup,
  getQuota,
  listLookups,
  runLookup,
} from "./api";
import { sampleLookup, sampleQuota } from "./fixtures";

const fetchMock = vi.fn<typeof fetch>();

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

beforeEach(() => {
  vi.stubGlobal("fetch", fetchMock);
  vi.stubEnv("NEXT_PUBLIC_API_URL", "http://api.test");
});

describe("api client", () => {
  it("unwraps data and sends X-Analyst-Id", async () => {
    fetchMock.mockResolvedValue(json({ data: sampleQuota() }));
    const quota = await getQuota("a1");
    expect(quota.remaining).toBe(3);
    const [url, init] = fetchMock.mock.calls[0] ?? [];
    expect(url).toBe("http://api.test/api/v1/quota");
    expect(new Headers(init?.headers).get("X-Analyst-Id")).toBe("a1");
  });

  it("sends Idempotency-Key and JSON body on create", async () => {
    fetchMock.mockResolvedValue(json({ data: sampleLookup() }, 201));
    await createLookup("a1", "key-1", { indicator: "evil.com" });
    const [, init] = fetchMock.mock.calls[0] ?? [];
    const headers = new Headers(init?.headers);
    expect(init?.method).toBe("POST");
    expect(headers.get("Idempotency-Key")).toBe("key-1");
    expect(headers.get("Content-Type")).toBe("application/json");
    expect(init?.body).toBe(JSON.stringify({ indicator: "evil.com" }));
  });

  it("encodes the requested_by filter", async () => {
    fetchMock.mockResolvedValue(json({ data: [sampleLookup()] }));
    await listLookups("a 1");
    expect(fetchMock.mock.calls[0]?.[0]).toBe(
      "http://api.test/api/v1/lookups?requested_by=a%201",
    );
  });

  it("throws a typed ApiError from the error envelope", async () => {
    fetchMock.mockResolvedValue(
      json({ error: { code: "lease_expired", message: "gone" } }, 409),
    );
    const err = await runLookup("a1", "l1").catch((e: unknown) => e);
    expect(err).toBeInstanceOf(ApiError);
    expect(err).toMatchObject({
      code: "lease_expired",
      status: 409,
      message: "gone",
    });
  });

  it("falls back to an unknown error for non-JSON failures", async () => {
    fetchMock.mockResolvedValue(new Response("boom", { status: 502 }));
    await expect(getQuota("a1")).rejects.toMatchObject({
      code: "unknown_error",
      status: 502,
    });
  });

  it("maps network failures to network_error", async () => {
    fetchMock.mockRejectedValue(new TypeError("failed"));
    await expect(getQuota("a1")).rejects.toMatchObject({
      code: "network_error",
      status: 0,
    });
  });

  it("rejects malformed success payloads", async () => {
    fetchMock.mockResolvedValue(json({ data: { nope: true } }));
    await expect(getQuota("a1")).rejects.toMatchObject({
      code: "unknown_error",
    });
  });
});
