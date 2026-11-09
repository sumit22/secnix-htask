<?php

declare(strict_types=1);

namespace App\Modules\Quota\Application;

use App\Modules\Quota\Contracts\QuotaLedger;
use App\Modules\Quota\Contracts\QuotaSnapshot;
use App\Modules\Quota\Domain\Models\QuotaAllocation;
use App\Modules\Quota\Domain\Repositories\QuotaAllocationRepository;
use Carbon\CarbonImmutable;

final readonly class QuotaLedgerService implements QuotaLedger
{
    public function __construct(
        private QuotaAllocationRepository $allocations,
        private int $dailyLimit,
    ) {}

    public function lock(string $providerId, CarbonImmutable $at): QuotaSnapshot
    {
        $allocation = $this->allocations->ensureOrgAllocation($providerId, $at, $this->dailyLimit);

        return self::toSnapshot($this->allocations->lockById($allocation->id));
    }

    public function snapshot(string $providerId, CarbonImmutable $at): QuotaSnapshot
    {
        return self::toSnapshot($this->allocations->ensureOrgAllocation($providerId, $at, $this->dailyLimit));
    }

    public function reserve(string $allocationId): void
    {
        $this->allocations->adjust($allocationId, 1, 0);
    }

    public function release(string $allocationId): void
    {
        $this->allocations->adjust($allocationId, -1, 0);
    }

    public function consume(string $allocationId): void
    {
        $this->allocations->adjust($allocationId, -1, 1);
    }

    private static function toSnapshot(QuotaAllocation $a): QuotaSnapshot
    {
        return new QuotaSnapshot($a->id, $a->quota_limit, $a->reserved, $a->consumed, $a->period_start, $a->period_end);
    }
}
