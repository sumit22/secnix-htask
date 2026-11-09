<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\IndicatorType;
use App\Enums\LookupStatus;
use App\Enums\Verdict;
use App\Models\LookupQueueEntry;
use App\Models\LookupRequest;
use App\Models\Provider;
use App\Models\QuotaAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LookupModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_request_casts_domain_types(): void
    {
        $lookup = LookupRequest::factory()->completed(Verdict::Malicious)->create()->refresh();

        self::assertSame(LookupStatus::Completed, $lookup->status);
        self::assertSame(IndicatorType::Domain, $lookup->indicator_type);
        self::assertSame('malicious', $lookup->result['verdict'] ?? null);
        self::assertInstanceOf(CarbonImmutable::class, $lookup->completed_at);
    }

    public function test_lookup_request_relations(): void
    {
        $lookup = LookupRequest::factory()->create();

        self::assertTrue($lookup->provider->is(Provider::query()->firstOrFail()));
        self::assertTrue($lookup->requester->team->is($lookup->team));
    }

    public function test_active_scope_returns_queued_and_leased_only(): void
    {
        LookupRequest::factory()->queued()->create();
        LookupRequest::factory()->leased()->create();
        LookupRequest::factory()->completed()->create();
        LookupRequest::factory()->expired()->create();

        self::assertSame(2, LookupRequest::query()->active()->count());
    }

    public function test_queue_entry_links_to_its_lookup(): void
    {
        $lookup = LookupRequest::factory()->queued()->create();
        $entry = LookupQueueEntry::query()->create([
            'provider_id' => $lookup->provider_id,
            'lookup_request_id' => $lookup->id,
        ]);

        self::assertTrue($entry->lookupRequest->is($lookup));
        self::assertTrue($lookup->queueEntry?->is($entry));
    }

    public function test_quota_remaining_is_limit_minus_reserved_and_consumed(): void
    {
        $quota = QuotaAllocation::factory()->create(['quota_limit' => 10, 'reserved' => 3, 'consumed' => 4]);

        self::assertSame(3, $quota->remaining());
    }

    public function test_ensure_org_allocation_creates_one_row_per_utc_day(): void
    {
        $provider = Provider::factory()->create();
        $now = CarbonImmutable::parse('2026-11-09 23:30:00', 'Asia/Kolkata'); // 18:00 UTC

        $first = QuotaAllocation::ensureOrgAllocation($provider, $now, 10);
        $second = QuotaAllocation::ensureOrgAllocation($provider, $now->addHour(), 99);

        self::assertTrue($first->is($second));
        self::assertSame(10, $second->quota_limit, 'existing allocation is never overwritten');
        self::assertSame('2026-11-09T00:00:00+00:00', $first->period_start->toIso8601String());
        self::assertSame('2026-11-10T00:00:00+00:00', $first->period_end->toIso8601String());
        self::assertNull($first->team_id);

        $nextDay = QuotaAllocation::ensureOrgAllocation($provider, CarbonImmutable::parse('2026-11-10 00:00:01', 'UTC'), 10);
        self::assertFalse($nextDay->is($first));
    }
}
