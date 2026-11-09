import type {
  ApiErrorCode,
  IndicatorType,
  LookupStatus,
  Verdict,
} from "./generated";

export interface LookupResult {
  verdict: Verdict;
  score: number;
  malware_families: string[];
  looked_up_at: string;
}

export interface LookupView {
  id: string;
  status: LookupStatus;
  indicator: { type: IndicatorType; value: string };
  provider: string;
  requested_by: string;
  deduplicated: boolean;
  lease: { leased_at: string; expires_at: string } | null;
  queue: { position: number } | null;
  result: LookupResult | null;
  created_at: string;
  completed_at: string | null;
  expired_at: string | null;
  server_time: string;
}

export interface QuotaView {
  provider: string;
  period: { start: string; end: string };
  limit: number;
  reserved: number;
  consumed: number;
  remaining: number;
  queued: number;
  lease_ttl_seconds: number;
  server_time: string;
}

export interface Analyst {
  id: string;
  name: string;
  team: { id: string; slug: string };
}

export interface ApiErrorBody {
  code: ApiErrorCode;
  message: string;
  fields?: Record<string, string[]>;
}

export interface DataEnvelope<T> {
  data: T;
}

export interface ErrorEnvelope {
  error: ApiErrorBody;
}

export interface CreateLookupBody {
  indicator: string;
  indicator_type?: IndicatorType;
  provider?: string;
}
