<?php

declare(strict_types=1);

namespace App\Modules\Quota\Infrastructure\Persistence;

use App\Modules\Quota\Domain\Models\QuotaAllocation;
use App\Modules\Quota\Domain\Repositories\QuotaAllocationRepository;
use Carbon\CarbonImmutable;

final class EloquentQuotaAllocationRepository implements QuotaAllocationRepository
{
    public function ensureOrgAllocation(string $providerId, CarbonImmutable $at, int $limit): QuotaAllocation
    {
        $start = $at->utc()->startOfDay();

        // ON CONFLICT DO NOTHING: concurrent callers converge on one row.
        QuotaAllocation::query()->insertOrIgnore([
            'provider_id' => $providerId,
            'team_id' => null,
            'period_start' => $start,
            'period_end' => $start->addDay(),
            'quota_limit' => $limit,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        return QuotaAllocation::query()
            ->where('provider_id', $providerId)
            ->whereNull('team_id')
            ->where('period_start', $start)
            ->sole();
    }

    public function lockById(string $id): QuotaAllocation
    {
        return QuotaAllocation::query()->lock('FOR NO KEY UPDATE')->findOrFail($id);
    }

    public function adjust(string $id, int $reservedDelta, int $consumedDelta): void
    {
        QuotaAllocation::query()->whereKey($id)->toBase()->incrementEach(
            ['reserved' => $reservedDelta, 'consumed' => $consumedDelta],
            ['updated_at' => now()],
        );
    }
}
