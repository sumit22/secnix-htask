<?php

declare(strict_types=1);

namespace App\Modules\Shared\Enums;

/** Machine-readable error codes returned in API error bodies (shared with the web app). */
enum ApiErrorCode: string
{
    case ValidationFailed = 'validation_failed';
    case AnalystNotFound = 'analyst_not_found';
    case IdempotencyKeyMissing = 'idempotency_key_missing';
    case IdempotencyKeyReused = 'idempotency_key_reused';
    case NotFound = 'not_found';
    case Forbidden = 'forbidden';
    case LeaseExpired = 'lease_expired';
    case LeaseNotActive = 'lease_not_active';
    case ProviderUnavailable = 'provider_unavailable';

    public function httpStatus(): int
    {
        return match ($this) {
            self::IdempotencyKeyMissing => 400,
            self::Forbidden => 403,
            self::NotFound => 404,
            self::LeaseExpired, self::LeaseNotActive => 409,
            self::ValidationFailed, self::AnalystNotFound, self::IdempotencyKeyReused => 422,
            self::ProviderUnavailable => 503,
        };
    }
}
