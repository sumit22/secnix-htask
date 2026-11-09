import { ApiError } from "./api";

const FRIENDLY: Record<string, string> = {
  validation_failed: "That input is not valid. Check the indicator and retry.",
  analyst_not_found: "Analyst not found. Pick an analyst.",
  idempotency_key_missing: "Request was missing its idempotency key.",
  idempotency_key_reused:
    "That submission key was already used for a different request.",
  not_found: "Lookup not found.",
  forbidden: "You cannot act on another team's lookup.",
  lease_expired: "Lease expired. Request the lookup again.",
  lease_not_active: "This lookup is still queued and has no active lease.",
  provider_unavailable: "Provider unavailable, retry.",
  network_error: "Network error. Retrying the same submission is safe.",
  unknown_error: "Something went wrong. Please try again.",
};

export function describeError(e: unknown): string {
  if (e instanceof ApiError) return FRIENDLY[e.code] ?? e.message;
  return "Something went wrong. Please try again.";
}
