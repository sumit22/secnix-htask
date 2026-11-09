"use client";

import { useEffect, useState } from "react";
import type { LookupView } from "../contracts/api";
import { formatMmSs, remainingSeconds } from "../lib/countdown";

interface Props {
  lookup: LookupView;
  /** Server clock minus client clock, captured when `lookup` arrived. */
  offsetMs: number;
  onRun: (id: string) => void;
  running?: boolean;
  error?: string | null;
}

function useNow(active: boolean): number {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    if (!active) return;
    const t = setInterval(() => {
      setNow(Date.now());
    }, 1000);
    return () => {
      clearInterval(t);
    };
  }, [active]);
  return now;
}

function Status({
  lookup,
  secondsLeft,
}: {
  lookup: LookupView;
  secondsLeft: number | null;
}) {
  switch (lookup.status) {
    case "queued":
      return <p>Queued — position {lookup.queue?.position ?? "?"}</p>;
    case "leased":
      return secondsLeft === 0 ? (
        <p>Lease expired</p>
      ) : (
        <p>
          Slot reserved. Lease expires in{" "}
          <strong className="mono">{formatMmSs(secondsLeft ?? 0)}</strong>
        </p>
      );
    case "expired":
      return <p>Lease expired. Request the lookup again.</p>;
    case "completed": {
      const r = lookup.result;
      if (!r) return <p>Completed</p>;
      return (
        <p>
          Verdict: <strong>{r.verdict}</strong> · Score {r.score}/100 · Malware
          families:{" "}
          {r.malware_families.length > 0
            ? r.malware_families.join(", ")
            : "none"}
        </p>
      );
    }
  }
}

export function LookupCard({ lookup, offsetMs, onRun, running, error }: Props) {
  const now = useNow(lookup.status === "leased");
  const secondsLeft =
    lookup.status === "leased" && lookup.lease
      ? remainingSeconds(lookup.lease.expires_at, offsetMs, now)
      : null;
  const canRun = lookup.status === "leased" && secondsLeft !== 0;

  return (
    <li className="card">
      <h3>
        <span className="mono">{lookup.indicator.value}</span>{" "}
        <span className="badge">{lookup.status}</span>
      </h3>
      <Status lookup={lookup} secondsLeft={secondsLeft} />
      {lookup.deduplicated && (
        <p className="note">
          Already in progress — shared with another request.
        </p>
      )}
      {canRun && (
        <button
          type="button"
          disabled={running === true}
          onClick={() => {
            onRun(lookup.id);
          }}
        >
          {running === true ? "Running…" : "Run lookup"}
        </button>
      )}
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
    </li>
  );
}
