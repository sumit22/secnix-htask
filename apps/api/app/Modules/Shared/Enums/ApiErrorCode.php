<?php

declare(strict_types=1);

namespace App\Modules\Shared\Enums;

/** Machine-readable error codes returned in API error bodies (shared with the web app). */
enum ApiErrorCode: string
{
    case ValidationFailed = 'validation_failed';
    case IdempotencyKeyReused = 'idempotency_key_reused';
    case LeaseExpired = 'lease_expired';
    case LeaseNotActive = 'lease_not_active';
    case NotFound = 'not_found';
    case AnalystNotFound = 'analyst_not_found';
}
