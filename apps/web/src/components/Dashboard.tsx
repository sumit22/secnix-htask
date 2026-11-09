"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import type { Analyst, LookupView, QuotaView } from "../contracts/api";
import { ACTIVE_LOOKUP_STATUSES } from "../contracts/generated";
import {
  ApiError,
  getLookup,
  getQuota,
  listAnalysts,
  listLookups,
  runLookup,
} from "../lib/api";
import { clockOffset } from "../lib/countdown";
import { describeError } from "../lib/messages";
import { LookupList, type TrackedLookup } from "./LookupList";
import { QuotaPanel } from "./QuotaPanel";
import { RequestForm } from "./RequestForm";

const STORAGE_KEY = "analystId";
const QUOTA_POLL_MS = 3000;
const LOOKUP_POLL_MS = 2000;

function track(view: LookupView): TrackedLookup {
  return { view, offsetMs: clockOffset(view.server_time, Date.now()) };
}

function readStored(): string | null {
  try {
    return localStorage.getItem(STORAGE_KEY);
  } catch {
    return null;
  }
}

function writeStored(id: string): void {
  try {
    localStorage.setItem(STORAGE_KEY, id);
  } catch {
    // Storage unavailable; the choice just will not persist.
  }
}

function isActive(v: LookupView): boolean {
  return (ACTIVE_LOOKUP_STATUSES as readonly string[]).includes(v.status);
}

export function Dashboard() {
  const [analysts, setAnalysts] = useState<Analyst[]>([]);
  const [analystId, setAnalystId] = useState<string | null>(null);
  const [quota, setQuota] = useState<QuotaView | null>(null);
  const [lookups, setLookups] = useState<TrackedLookup[]>([]);
  const [runningId, setRunningId] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [loadError, setLoadError] = useState<string | null>(null);
  const lookupsRef = useRef(lookups);
  lookupsRef.current = lookups;

  const upsert = useCallback((view: LookupView) => {
    const t = track(view);
    setLookups((cur) =>
      cur.some((l) => l.view.id === view.id)
        ? cur.map((l) => (l.view.id === view.id ? t : l))
        : [t, ...cur],
    );
  }, []);

  useEffect(() => {
    let cancelled = false;
    listAnalysts()
      .then((list) => {
        if (cancelled) return;
        setAnalysts(list);
        const stored = readStored();
        if (stored && list.some((a) => a.id === stored)) setAnalystId(stored);
      })
      .catch((e: unknown) => {
        if (!cancelled) setLoadError(describeError(e));
      });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    let cancelled = false;
    const tick = () => {
      getQuota(analystId)
        .then((q) => {
          if (!cancelled) setQuota(q);
        })
        .catch(() => undefined);
    };
    tick();
    const t = setInterval(tick, QUOTA_POLL_MS);
    return () => {
      cancelled = true;
      clearInterval(t);
    };
  }, [analystId]);

  useEffect(() => {
    if (!analystId) return;
    let cancelled = false;
    listLookups(analystId)
      .then((list) => {
        if (!cancelled) setLookups(list.map(track));
      })
      .catch((e: unknown) => {
        if (!cancelled) setLoadError(describeError(e));
      });
    const t = setInterval(() => {
      for (const { view } of lookupsRef.current) {
        if (!isActive(view)) continue;
        getLookup(analystId, view.id)
          .then((v) => {
            if (!cancelled) upsert(v);
          })
          .catch(() => undefined);
      }
    }, LOOKUP_POLL_MS);
    return () => {
      cancelled = true;
      clearInterval(t);
    };
  }, [analystId, upsert]);

  function selectAnalyst(id: string) {
    setAnalystId(id || null);
    setLookups([]);
    setErrors({});
    if (id) writeStored(id);
  }

  async function onRun(id: string) {
    if (!analystId) return;
    setRunningId(id);
    setErrors((cur) =>
      Object.fromEntries(Object.entries(cur).filter(([k]) => k !== id)),
    );
    try {
      upsert(await runLookup(analystId, id));
    } catch (e) {
      setErrors((cur) => ({ ...cur, [id]: describeError(e) }));
      // Refresh so an expired lease shows its real state.
      if (e instanceof ApiError && e.status === 409) {
        getLookup(analystId, id).then(upsert, () => undefined);
      }
    } finally {
      setRunningId(null);
    }
  }

  return (
    <main className="dash">
      <h1>Enrichment Lookup Coordinator</h1>

      <div className="form">
        <label htmlFor="analyst">Analyst</label>
        <select
          id="analyst"
          value={analystId ?? ""}
          onChange={(e) => {
            selectAnalyst(e.target.value);
          }}
        >
          <option value="">Select an analyst…</option>
          {analysts.map((a) => (
            <option key={a.id} value={a.id}>
              {a.name}
            </option>
          ))}
        </select>
      </div>

      {loadError && (
        <p role="alert" className="error">
          {loadError}
        </p>
      )}

      <QuotaPanel quota={quota} />
      <RequestForm analystId={analystId} onCreated={upsert} />
      <LookupList
        lookups={lookups}
        runningId={runningId}
        errors={errors}
        onRun={(id) => void onRun(id)}
      />
    </main>
  );
}
