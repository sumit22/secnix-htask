import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { sampleLookup } from "../lib/fixtures";
import { RequestForm } from "./RequestForm";

const fetchMock = vi.fn<typeof fetch>();

function ok(): Response {
  return new Response(JSON.stringify({ data: sampleLookup() }), {
    status: 201,
  });
}

function submit(value: string) {
  fireEvent.change(screen.getByLabelText("Indicator"), { target: { value } });
  fireEvent.click(screen.getByRole("button", { name: "Request lookup" }));
}

function keyOf(call: number): string | null {
  const init = fetchMock.mock.calls[call]?.[1];
  return new Headers(init?.headers).get("Idempotency-Key");
}

beforeEach(() => {
  vi.stubGlobal("fetch", fetchMock);
  vi.stubEnv("NEXT_PUBLIC_API_URL", "http://api.test");
});

describe("RequestForm", () => {
  it("sends Idempotency-Key and X-Analyst-Id", async () => {
    fetchMock.mockResolvedValue(ok());
    const onCreated = vi.fn();
    render(<RequestForm analystId="a1" onCreated={onCreated} />);
    submit(" evil.example.com ");
    await waitFor(() => {
      expect(onCreated).toHaveBeenCalled();
    });
    const [url, init] = fetchMock.mock.calls[0] ?? [];
    const headers = new Headers(init?.headers);
    expect(url).toBe("http://api.test/api/v1/lookups");
    expect(headers.get("X-Analyst-Id")).toBe("a1");
    expect(headers.get("Idempotency-Key")).toMatch(/^[0-9a-f-]{36}$/);
    expect(init?.body).toBe(JSON.stringify({ indicator: "evil.example.com" }));
  });

  it("reuses the key when retrying after a network error", async () => {
    fetchMock.mockRejectedValueOnce(new TypeError("offline"));
    fetchMock.mockResolvedValue(ok());
    render(<RequestForm analystId="a1" onCreated={vi.fn()} />);
    submit("evil.example.com");
    expect(await screen.findByRole("alert")).toHaveTextContent(/network/i);
    fireEvent.click(screen.getByRole("button", { name: "Request lookup" }));
    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(2);
    });
    expect(keyOf(1)).toBe(keyOf(0));
  });

  it("uses a fresh key for the next submission after success", async () => {
    fetchMock.mockImplementation(() => Promise.resolve(ok()));
    render(<RequestForm analystId="a1" onCreated={vi.fn()} />);
    submit("evil.example.com");
    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });
    await waitFor(() => {
      expect(screen.getByLabelText("Indicator")).toHaveValue("");
    });
    submit("evil.example.com");
    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(2);
    });
    expect(keyOf(1)).not.toBe(keyOf(0));
  });

  it("disables submit until an analyst is chosen", () => {
    render(<RequestForm analystId={null} onCreated={vi.fn()} />);
    expect(
      screen.getByRole("button", { name: "Request lookup" }),
    ).toBeDisabled();
  });
});
