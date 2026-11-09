<?php

declare(strict_types=1);

namespace App\Modules\Shared\Exceptions;

use App\Modules\Shared\Enums\ApiErrorCode;
use RuntimeException;

/** Expected business failure; rendered as `{ error: { code, message } }` with the code's HTTP status. */
final class DomainError extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly ApiErrorCode $errorCode,
        string $message,
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }
}
