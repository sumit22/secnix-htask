import type { QuotaView } from "../contracts/api";

export function QuotaPanel({ quota }: { quota: QuotaView | null }) {
  const items: [string, number | undefined][] = [
    ["Limit", quota?.limit],
    ["Reserved", quota?.reserved],
    ["Consumed", quota?.consumed],
    ["Remaining", quota?.remaining],
    ["Queued", quota?.queued],
  ];
  return (
    <section aria-labelledby="quota-h">
      <h2 id="quota-h">Quota{quota ? ` — ${quota.provider}` : ""}</h2>
      <dl className="quota">
        {items.map(([label, value]) => (
          <div key={label}>
            <dt>{label}</dt>
            <dd>{value ?? "–"}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}
