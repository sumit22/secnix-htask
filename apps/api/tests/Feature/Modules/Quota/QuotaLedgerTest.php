<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Quota;

use App\Modules\Providers\Domain\Models\Provider;
use App\Modules\Quota\Contracts\QuotaLedger;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class QuotaLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserve_release_consume_move_units(): void
    {
        config(['enrichment.default_daily_quota' => 3]);
        $ledger = $this->app->make(QuotaLedger::class);
        $providerId = Provider::factory()->create()->id;
        $now = CarbonImmutable::now();

        $snapshot = DB::transaction(static function () use ($ledger, $providerId, $now) {
            $locked = $ledger->lock($providerId, $now);
            $ledger->reserve($locked->allocationId);
            $ledger->reserve($locked->allocationId);
            $ledger->release($locked->allocationId);
            $ledger->consume($locked->allocationId);

            return $ledger->snapshot($providerId, $now);
        });

        self::assertSame(3, $snapshot->limit);
        self::assertSame(0, $snapshot->reserved);
        self::assertSame(1, $snapshot->consumed);
        self::assertSame(2, $snapshot->remaining());
    }

    public function test_the_database_refuses_to_reserve_beyond_the_limit(): void
    {
        config(['enrichment.default_daily_quota' => 1]);
        $ledger = $this->app->make(QuotaLedger::class);
        $allocationId = $ledger->snapshot(Provider::factory()->create()->id, CarbonImmutable::now())->allocationId;
        $ledger->reserve($allocationId);

        $this->expectException(QueryException::class);

        $ledger->reserve($allocationId);
    }
}
