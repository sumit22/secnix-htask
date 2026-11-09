<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Quota;

use App\Modules\Providers\Domain\Models\Provider;
use App\Modules\Quota\Domain\Models\QuotaAllocation;
use App\Modules\Quota\Domain\Repositories\QuotaAllocationRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QuotaAllocationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_is_limit_minus_reserved_and_consumed(): void
    {
        $quota = QuotaAllocation::factory()->create(['quota_limit' => 10, 'reserved' => 3, 'consumed' => 4]);

        self::assertSame(3, $quota->remaining());
    }

    public function test_ensure_org_allocation_creates_one_row_per_utc_day(): void
    {
        $repository = $this->app->make(QuotaAllocationRepository::class);
        $providerId = Provider::factory()->create()->id;
        $now = CarbonImmutable::parse('2026-11-09 23:30:00', 'Asia/Kolkata'); // 18:00 UTC

        $first = $repository->ensureOrgAllocation($providerId, $now, 10);
        $second = $repository->ensureOrgAllocation($providerId, $now->addHour(), 99);

        self::assertTrue($first->is($second));
        self::assertSame(10, $second->quota_limit, 'existing allocation is never overwritten');
        self::assertSame('2026-11-09T00:00:00+00:00', $first->period_start->toIso8601String());
        self::assertSame('2026-11-10T00:00:00+00:00', $first->period_end->toIso8601String());
        self::assertNull($first->team_id);

        $nextDay = $repository->ensureOrgAllocation($providerId, CarbonImmutable::parse('2026-11-10 00:00:01', 'UTC'), 10);
        self::assertFalse($nextDay->is($first));
    }
}
