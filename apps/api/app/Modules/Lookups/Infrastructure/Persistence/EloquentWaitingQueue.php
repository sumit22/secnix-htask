<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Infrastructure\Persistence;

use App\Modules\Lookups\Domain\Models\LookupQueueEntry;
use App\Modules\Lookups\Domain\Repositories\WaitingQueue;

final class EloquentWaitingQueue implements WaitingQueue
{
    public function enqueue(string $providerId, string $lookupId): void
    {
        LookupQueueEntry::query()->create(['provider_id' => $providerId, 'lookup_request_id' => $lookupId]);
    }

    public function claimHead(string $providerId): ?string
    {
        $head = LookupQueueEntry::query()
            ->where('provider_id', $providerId)
            ->orderBy('id')
            ->lock('FOR UPDATE SKIP LOCKED')
            ->first();

        if ($head === null) {
            return null;
        }

        $head->delete();

        return $head->lookup_request_id;
    }

    public function positionOf(string $lookupId): ?int
    {
        $entry = LookupQueueEntry::query()->where('lookup_request_id', $lookupId)->first();

        if ($entry === null) {
            return null;
        }

        return LookupQueueEntry::query()
            ->where('provider_id', $entry->provider_id)
            ->where('id', '<=', $entry->id)
            ->count();
    }

    public function size(string $providerId): int
    {
        return LookupQueueEntry::query()->where('provider_id', $providerId)->count();
    }
}
