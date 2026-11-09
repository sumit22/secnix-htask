<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Infrastructure\Persistence;

use App\Modules\Lookups\Domain\Models\IdempotencyKey;
use App\Modules\Lookups\Domain\Repositories\IdempotencyKeyRepository;

final class EloquentIdempotencyKeyRepository implements IdempotencyKeyRepository
{
    public function find(string $requesterId, string $key): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->where('requested_by', $requesterId)
            ->where('idempotency_key', $key)
            ->first();
    }

    public function record(string $requesterId, string $key, string $requestHash, string $lookupId, bool $deduplicated): void
    {
        IdempotencyKey::query()->create([
            'requested_by' => $requesterId,
            'idempotency_key' => $key,
            'request_hash' => $requestHash,
            'lookup_request_id' => $lookupId,
            'deduplicated' => $deduplicated,
        ]);
    }
}
