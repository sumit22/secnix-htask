<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Lookups;

use App\Modules\Lookups\Contracts\Enums\LookupStatus;
use App\Modules\Lookups\Domain\Models\LookupQueueEntry;
use App\Modules\Lookups\Domain\Models\LookupRequest;
use App\Modules\Providers\Contracts\Enums\Verdict;
use App\Modules\Shared\Indicators\IndicatorType;
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
}
