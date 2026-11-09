<?php

declare(strict_types=1);

namespace App\Modules\Quota\Contracts;

use Carbon\CarbonImmutable;

/**
 * Org-level provider quota. Mutating methods must run inside the caller's transaction;
 * lock() serialises all reservations for a provider until that transaction ends.
 */
interface QuotaLedger
{
    /** Today's allocation (created on first use), row-locked FOR UPDATE. */
    public function lock(string $providerId, CarbonImmutable $at): QuotaSnapshot;

    /** Read-only view for dashboards. */
    public function snapshot(string $providerId, CarbonImmutable $at): QuotaSnapshot;

    /** Hold one unit for a lease. Caller must have checked remaining() under lock(). */
    public function reserve(string $allocationId): void;

    /** Lease expired: the held unit returns to the pool. */
    public function release(string $allocationId): void;

    /** Lookup ran: the held unit is spent for good. */
    public function consume(string $allocationId): void;
}
