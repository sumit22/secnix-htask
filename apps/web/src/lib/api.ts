import type {
  Analyst,
  CreateLookupBody,
  LookupView,
  QuotaView,
} from "../contracts/api";
import type { ApiErrorCode } from "../contracts/generated";
import {
  isAnalyst,
  isApiErrorEnvelope,
  isLookupView,
  isQuotaView,
} from "../contracts/validate";
import { readEnv } from "./env";

export type ClientErrorCode = "network_error" | "unknown_error";

export class ApiError extends Error {
  readonly code: ApiErrorCode | ClientErrorCode;
  readonly status: number;

  constructor(
    code: ApiErrorCode | ClientErrorCode,
    status: number,
    message: string,
  ) {
    super(message);
    this.name = "ApiError";
    this.code = code;
    this.status = status;
  }
}

function listOf<T>(guard: (v: unknown) => v is T): (v: unknown) => v is T[] {
  return (v): v is T[] => Array.isArray(v) && v.every(guard);
}

interface RequestOptions {
  method?: "GET" | "POST";
  analystId?: string | null;
  headers?: Record<string, string>;
  body?: unknown;
}

async function request<T>(
  path: string,
  guard: (v: unknown) => v is T,
  opts: RequestOptions = {},
): Promise<T> {
  const headers = new Headers(opts.headers);
  if (opts.analystId) headers.set("X-Analyst-Id", opts.analystId);
  const init: RequestInit = { method: opts.method ?? "GET", headers };
  if (opts.body !== undefined) {
    headers.set("Content-Type", "application/json");
    init.body = JSON.stringify(opts.body);
  }

  let res: Response;
  try {
    res = await fetch(`${readEnv().apiUrl}/api/v1${path}`, init);
  } catch {
    throw new ApiError("network_error", 0, "Network error");
  }

  const payload: unknown = await res.json().catch(() => null);
  if (!res.ok) {
    if (isApiErrorEnvelope(payload)) {
      throw new ApiError(payload.error.code, res.status, payload.error.message);
    }
    throw new ApiError(
      "unknown_error",
      res.status,
      `Request failed (${String(res.status)})`,
    );
  }
  if (
    typeof payload === "object" &&
    payload !== null &&
    "data" in payload &&
    guard(payload.data)
  )
    return payload.data;
  throw new ApiError("unknown_error", res.status, "Unexpected response shape");
}

export function getQuota(analystId: string | null): Promise<QuotaView> {
  return request("/quota", isQuotaView, { analystId });
}

export function listAnalysts(): Promise<Analyst[]> {
  return request("/analysts", listOf(isAnalyst));
}

export function listLookups(analystId: string): Promise<LookupView[]> {
  const q = encodeURIComponent(analystId);
  return request(`/lookups?requested_by=${q}`, listOf(isLookupView), {
    analystId,
  });
}

export function getLookup(analystId: string, id: string): Promise<LookupView> {
  return request(`/lookups/${encodeURIComponent(id)}`, isLookupView, {
    analystId,
  });
}

export function createLookup(
  analystId: string,
  idempotencyKey: string,
  body: CreateLookupBody,
): Promise<LookupView> {
  return request("/lookups", isLookupView, {
    method: "POST",
    analystId,
    headers: { "Idempotency-Key": idempotencyKey },
    body,
  });
}

export function runLookup(analystId: string, id: string): Promise<LookupView> {
  return request(`/lookups/${encodeURIComponent(id)}/run`, isLookupView, {
    method: "POST",
    analystId,
  });
}
