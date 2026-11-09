<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Domain\Repositories;

/** FIFO of requests waiting for quota, per provider. */
interface WaitingQueue
{
    public function enqueue(string $providerId, string $lookupId): void;

    /** Removes and returns the oldest waiting lookup id (SKIP LOCKED), or null when empty. */
    public function claimHead(string $providerId): ?string;

    /** 1 = next in line; null when not queued. */
    public function positionOf(string $lookupId): ?int;

    public function size(string $providerId): int;
}
