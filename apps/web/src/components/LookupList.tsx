import type { LookupView } from "../contracts/api";
import { LookupCard } from "./LookupCard";

export interface TrackedLookup {
  view: LookupView;
  offsetMs: number;
}

interface Props {
  lookups: TrackedLookup[];
  runningId: string | null;
  errors: Record<string, string>;
  onRun: (id: string) => void;
}

export function LookupList({ lookups, runningId, errors, onRun }: Props) {
  return (
    <section aria-labelledby="lookups-h">
      <h2 id="lookups-h">Recent lookups</h2>
      {lookups.length === 0 ? (
        <p>No lookups yet.</p>
      ) : (
        <ul className="list">
          {lookups.map(({ view, offsetMs }) => (
            <LookupCard
              key={view.id}
              lookup={view}
              offsetMs={offsetMs}
              onRun={onRun}
              running={runningId === view.id}
              error={errors[view.id] ?? null}
            />
          ))}
        </ul>
      )}
    </section>
  );
}
