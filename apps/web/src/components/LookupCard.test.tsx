import { act, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { sampleLookup } from "../lib/fixtures";
import { LookupCard } from "./LookupCard";

afterEach(() => {
  vi.useRealTimers();
});

describe("LookupCard", () => {
  it("shows queue position when queued", () => {
    render(<LookupCard lookup={sampleLookup()} offsetMs={0} onRun={vi.fn()} />);
    expect(screen.getByText("Queued — position 3")).toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: "Run lookup" }),
    ).not.toBeInTheDocument();
  });

  it("shows a live countdown and runs on click when leased", () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    vi.setSystemTime(Date.parse("2026-01-01T00:00:00Z"));
    const onRun = vi.fn();
    render(
      <LookupCard
        lookup={sampleLookup({
          id: "01a1c02f-0000-7000-8000-000000000009",
          status: "leased",
          queue: null,
          lease: {
            leased_at: "2026-01-01T00:00:00Z",
            expires_at: "2026-01-01T00:01:30Z",
          },
        })}
        offsetMs={0}
        onRun={onRun}
      />,
    );
    expect(screen.getByText(/01:30/)).toBeInTheDocument();
    act(() => {
      vi.advanceTimersByTime(2000);
    });
    expect(screen.getByText(/01:28/)).toBeInTheDocument();

    fireEvent.click(screen.getByRole("button", { name: "Run lookup" }));
    expect(onRun).toHaveBeenCalledWith("01a1c02f-0000-7000-8000-000000000009");
  });

  it("applies server clock offset to the countdown", () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    vi.setSystemTime(Date.parse("2026-01-01T00:00:00Z"));
    render(
      <LookupCard
        lookup={sampleLookup({
          status: "leased",
          queue: null,
          lease: {
            leased_at: "2026-01-01T00:00:00Z",
            expires_at: "2026-01-01T00:01:30Z",
          },
        })}
        offsetMs={30_000}
        onRun={vi.fn()}
      />,
    );
    expect(screen.getByText(/01:00/)).toBeInTheDocument();
  });

  it("shows expired state without a run button", () => {
    render(
      <LookupCard
        lookup={sampleLookup({ status: "expired", queue: null })}
        offsetMs={0}
        onRun={vi.fn()}
      />,
    );
    expect(screen.getByText(/lease expired/i)).toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: "Run lookup" }),
    ).not.toBeInTheDocument();
  });

  it("shows the result when completed", () => {
    render(
      <LookupCard
        lookup={sampleLookup({
          status: "completed",
          queue: null,
          result: {
            verdict: "malicious",
            score: 87,
            malware_families: ["emotet", "trickbot"],
            looked_up_at: "2026-01-01T00:00:00Z",
          },
        })}
        offsetMs={0}
        onRun={vi.fn()}
      />,
    );
    expect(screen.getByText(/malicious/i)).toBeInTheDocument();
    expect(screen.getByText(/87/)).toBeInTheDocument();
    expect(screen.getByText(/emotet, trickbot/)).toBeInTheDocument();
  });

  it("notes deduplicated lookups and shows errors", () => {
    render(
      <LookupCard
        lookup={sampleLookup({ deduplicated: true })}
        offsetMs={0}
        onRun={vi.fn()}
        error="Provider unavailable, retry."
      />,
    );
    expect(
      screen.getByText(/already in progress — shared/i),
    ).toBeInTheDocument();
    expect(screen.getByRole("alert")).toHaveTextContent(
      "Provider unavailable, retry.",
    );
  });
});
