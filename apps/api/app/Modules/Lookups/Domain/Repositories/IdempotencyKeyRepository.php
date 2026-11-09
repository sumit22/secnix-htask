<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Domain\Repositories;

use App\Modules\Lookups\Domain\Models\IdempotencyKey;

interface IdempotencyKeyRepository
{
    public function find(string $requesterId, string $key): ?IdempotencyKey;

    public function record(string $requesterId, string $key, string $requestHash, string $lookupId, bool $deduplicated): void;
}
