import type {
  Analyst,
  CreateLookupBody,
  LookupView,
  QuotaView,
} from "../contracts/api";
import { ApiErrorCode } from "../contracts/generated";
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

type Json = Record<string, unknown>;

function isRecord(v: unknown): v is Json {
  return typeof v === "object" && v !== null && !Array.isArray(v);
}

const ERROR_CODES: readonly string[] = Object.values(ApiErrorCode);

function isErrorCode(v: unknown): v is ApiErrorCode {
  return typeof v === "string" && ERROR_CODES.includes(v);
}

// Minimal shape checks on the fields the UI relies on.
function isLookupView(v: unknown): v is LookupView {
  return (
    isRecord(v) &&
    typeof v.id === "string" &&
    typeof v.status === "string" &&
    isRecord(v.indicator) &&
    typeof v.server_time === "string"
  );
}

function isQuotaView(v: unknown): v is QuotaView {
  return (
    isRecord(v) &&
    typeof v.limit === "number" &&
    typeof v.remaining === "number" &&
    typeof v.server_time === "string"
  );
}

function isAnalyst(v: unknown): v is Analyst {
  return isRecord(v) && typeof v.id === "string" && typeof v.name === "string";
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
    const err = isRecord(payload) ? payload.error : undefined;
    if (isRecord(err) && isErrorCode(err.code)) {
      const message = typeof err.message === "string" ? err.message : err.code;
      throw new ApiError(err.code, res.status, message);
    }
    throw new ApiError(
      "unknown_error",
      res.status,
      `Request failed (${String(res.status)})`,
    );
  }
  if (isRecord(payload) && guard(payload.data)) return payload.data;
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
