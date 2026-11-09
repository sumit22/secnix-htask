import type { IndicatorType } from "./generated";
// Response shapes are generated from contracts/schemas (single source shared with the API).
export type { Analyst, LookupView, QuotaView } from "./api.generated";
import type { ApiErrorEnvelope, LookupView } from "./api.generated";

export type LookupResult = NonNullable<LookupView["result"]>;
export type ApiErrorBody = ApiErrorEnvelope["error"];

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
