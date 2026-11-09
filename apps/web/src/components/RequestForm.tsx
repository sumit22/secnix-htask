"use client";

import { useRef, useState, type SubmitEvent } from "react";
import type { LookupView } from "../contracts/api";
import { ApiError, createLookup } from "../lib/api";
import { describeError } from "../lib/messages";

interface Props {
  analystId: string | null;
  onCreated: (lookup: LookupView) => void;
}

interface Pending {
  key: string;
  analystId: string;
  indicator: string;
}

export function RequestForm({ analystId, onCreated }: Props) {
  const [indicator, setIndicator] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // Survives a network failure so a retry of the same submission reuses its key.
  const pending = useRef<Pending | null>(null);

  async function onSubmit(e: SubmitEvent) {
    e.preventDefault();
    const value = indicator.trim();
    if (!analystId || !value || busy) return;

    const prev = pending.current;
    const same = prev?.analystId === analystId && prev.indicator === value;
    const key = prev && same ? prev.key : crypto.randomUUID();
    pending.current = { key, analystId, indicator: value };

    setBusy(true);
    setError(null);
    try {
      const lookup = await createLookup(analystId, key, { indicator: value });
      pending.current = null;
      setIndicator("");
      onCreated(lookup);
    } catch (err) {
      // Only an unknown outcome (network) keeps the key; a server answer ends the submission.
      if (!(err instanceof ApiError && err.code === "network_error")) {
        pending.current = null;
      }
      setError(describeError(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={(e) => void onSubmit(e)} className="form">
      <label htmlFor="indicator">Indicator</label>
      <div className="row">
        <input
          id="indicator"
          value={indicator}
          placeholder="IP, domain or file hash"
          onChange={(e) => {
            setIndicator(e.target.value);
          }}
        />
        <button
          type="submit"
          disabled={!analystId || busy || !indicator.trim()}
        >
          {busy ? "Requesting…" : "Request lookup"}
        </button>
      </div>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
    </form>
  );
}
