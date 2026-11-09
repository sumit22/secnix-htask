<?php

declare(strict_types=1);

namespace App\Modules\Quota\Domain\Repositories;

use App\Modules\Quota\Domain\Models\QuotaAllocation;
use Carbon\CarbonImmutable;

interface QuotaAllocationRepository
{
    /** Org-level allocation for the UTC day containing $at; created with $limit if missing, never modified. */
    public function ensureOrgAllocation(string $providerId, CarbonImmutable $at, int $limit): QuotaAllocation;

    public function lockById(string $id): QuotaAllocation;

    /** Atomic counter change; the DB CHECK rejects any result above the limit or below zero. */
    public function adjust(string $id, int $reservedDelta, int $consumedDelta): void;
}
