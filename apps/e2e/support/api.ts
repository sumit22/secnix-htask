import { randomUUID } from "node:crypto";

export const API = "http://localhost:8000/api/v1";

export interface Analyst {
  id: string;
  name: string;
}
export interface Lookup {
  id: string;
  status: "queued" | "leased" | "completed" | "expired";
  deduplicated: boolean;
  queue: { position: number } | null;
  indicator: { value: string };
}
export interface Quota {
  limit: number;
  reserved: number;
  consumed: number;
  remaining: number;
  queued: number;
}

async function call<T>(
  method: string,
  p: string,
  analyst?: string,
  body?: unknown,
  extra: Record<string, string> = {},
): Promise<{ status: number; data: T }> {
  const res = await fetch(`${API}${p}`, {
    method,
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...(analyst ? { "X-Analyst-Id": analyst } : {}),
      ...extra,
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const json = (await res.json()) as { data: T };
  return { status: res.status, data: json.data };
}

export async function analysts(): Promise<Record<string, Analyst>> {
  const { data } = await call<Analyst[]>("GET", "/analysts");
  const out: Record<string, Analyst> = {};
  for (const a of data) out[a.name.split(" ")[0]!] = a;
  return out;
}

export async function requestLookup(
  analystId: string,
  indicator: string,
  key: string = randomUUID(),
) {
  return call<Lookup>("POST", "/lookups", analystId, { indicator }, { "Idempotency-Key": key });
}

export async function getQuota(analystId: string): Promise<Quota> {
  return (await call<Quota>("GET", "/quota?provider=mock-intel", analystId)).data;
}

export async function getLookup(analystId: string, id: string): Promise<Lookup> {
  return (await call<Lookup>("GET", `/lookups/${id}`, analystId)).data;
}
