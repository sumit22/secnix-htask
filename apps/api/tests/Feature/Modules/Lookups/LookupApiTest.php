<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Lookups;

use App\Modules\Identity\Domain\Models\Analyst;
use App\Modules\Identity\Domain\Models\Team;
use App\Modules\Lookups\Contracts\ExpireLeases;
use App\Modules\Providers\Application\ProviderCatalogService;
use App\Modules\Providers\Contracts\ProviderCatalog;
use App\Modules\Providers\Contracts\ProviderResult;
use App\Modules\Providers\Contracts\ThreatIntelProvider;
use App\Modules\Providers\Domain\Models\Provider;
use App\Modules\Providers\Domain\Repositories\ProviderRepository;
use App\Modules\Quota\Domain\Models\QuotaAllocation;
use App\Modules\Shared\Enums\ApiErrorCode;
use App\Modules\Shared\Exceptions\DomainError;
use App\Modules\Shared\Indicators\Indicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/** End-to-end API behaviour against real Postgres: every branch of docs/api.md. */
final class LookupApiTest extends TestCase
{
    use RefreshDatabase;

    private Analyst $alice;

    private Analyst $bob;

    protected function setUp(): void
    {
        parent::setUp();
        config(['enrichment.default_daily_quota' => 2, 'enrichment.lease_ttl_seconds' => 300]);
        Provider::factory()->create(['slug' => 'mock-intel']);
        $team = Team::factory()->create();
        $this->alice = Analyst::factory()->for($team)->create();
        $this->bob = Analyst::factory()->for($team)->create();
    }

    // ------------------------------------------------------------- request

    public function test_reserves_a_lease_when_quota_is_available(): void
    {
        $response = $this->request('evil.example.com')->assertCreated();

        $response->assertHeader('Location', url('/api/v1/lookups/'.$this->str($response->json('data.id'))))
            ->assertJsonPath('data.status', 'leased')
            ->assertJsonPath('data.indicator', ['type' => 'domain', 'value' => 'evil.example.com'])
            ->assertJsonPath('data.queue', null)
            ->assertJsonPath('data.deduplicated', false);
        self::assertNotNull($response->json('data.lease.expires_at'));
        $this->assertQuota(reserved: 1, consumed: 0);
    }

    public function test_queues_in_arrival_order_when_quota_is_exhausted(): void
    {
        $this->request('a.example.com');
        $this->request('b.example.com');

        $this->request('c.example.com')->assertCreated()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.queue.position', 1)
            ->assertJsonPath('data.lease', null);
        $this->request('d.example.com')->assertJsonPath('data.queue.position', 2);
        $this->assertQuota(reserved: 2, consumed: 0);
    }

    public function test_retry_with_same_key_replays_without_spending_a_unit(): void
    {
        $first = $this->request('evil.example.com', key: 'k-1');

        $this->request('EVIL.example.com.', key: 'k-1')
            ->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertQuota(reserved: 1, consumed: 0);
    }

    public function test_reusing_a_key_for_a_different_request_is_rejected(): void
    {
        $this->request('a.example.com', key: 'k-1');

        $this->request('b.example.com', key: 'k-1')
            ->assertStatus(422)->assertJsonPath('error.code', ApiErrorCode::IdempotencyKeyReused->value);
    }

    public function test_active_indicator_is_shared_without_spending_a_unit(): void
    {
        $first = $this->request('evil.example.com', as: $this->alice);

        $this->request('evil.example.com', as: $this->bob)
            ->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.deduplicated', true);
        $this->assertQuota(reserved: 1, consumed: 0);
    }

    public function test_retry_of_a_deduplicated_request_after_completion_does_not_spend_a_unit(): void
    {
        $first = $this->request('evil.example.com', as: $this->alice);
        $this->request('evil.example.com', as: $this->bob, key: 'bob-1');
        $this->runLookup($first->json('data.id'))->assertOk();

        $this->request('evil.example.com', as: $this->bob, key: 'bob-1')
            ->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.status', 'completed');
        $this->assertQuota(reserved: 0, consumed: 1);
    }

    public function test_finished_indicator_can_be_requested_again(): void
    {
        $first = $this->request('evil.example.com');
        $this->runLookup($first->json('data.id'));

        $this->request('evil.example.com')->assertCreated()->assertJsonPath('data.status', 'leased');
        $this->assertQuota(reserved: 1, consumed: 1);
    }

    public function test_missing_idempotency_key_is_rejected(): void
    {
        $this->withHeaders(['X-Analyst-Id' => $this->alice->id])
            ->postJson('/api/v1/lookups', ['indicator' => '8.8.8.8'])
            ->assertStatus(400)->assertJsonPath('error.code', ApiErrorCode::IdempotencyKeyMissing->value);
    }

    public function test_overlong_idempotency_key_is_rejected(): void
    {
        $this->request('8.8.8.8', key: str_repeat('k', 101))
            ->assertStatus(400)->assertJsonPath('error.code', ApiErrorCode::IdempotencyKeyMissing->value);
    }

    public function test_unknown_or_missing_analyst_is_rejected(): void
    {
        $this->withHeaders(['Idempotency-Key' => 'k', 'X-Analyst-Id' => (string) Str::uuid7()])
            ->postJson('/api/v1/lookups', ['indicator' => '8.8.8.8'])
            ->assertStatus(422)->assertJsonPath('error.code', ApiErrorCode::AnalystNotFound->value);

        $this->getJson('/api/v1/quota')->assertStatus(422)->assertJsonPath('error.code', ApiErrorCode::AnalystNotFound->value);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidBodies(): iterable
    {
        yield 'missing indicator' => [[], 'indicator'];
        yield 'not a string' => [['indicator' => ['x']], 'indicator'];
        yield 'garbage indicator' => [['indicator' => 'not an indicator!'], 'indicator'];
        yield 'type mismatch' => [['indicator' => '8.8.8.8', 'indicator_type' => 'domain'], 'indicator'];
        yield 'unknown type' => [['indicator' => '8.8.8.8', 'indicator_type' => 'url'], 'indicator_type'];
    }

    /** @param array<string, mixed> $body */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidBodies')]
    public function test_invalid_input_is_rejected(array $body, string $field): void
    {
        $this->withHeaders(['Idempotency-Key' => 'k', 'X-Analyst-Id' => $this->alice->id])
            ->postJson('/api/v1/lookups', $body)
            ->assertStatus(422)
            ->assertJsonPath('error.code', ApiErrorCode::ValidationFailed->value)
            ->assertJsonStructure(['error' => ['fields' => [$field]]]);
        $this->assertQuota(reserved: 0, consumed: 0);
    }

    public function test_unknown_provider_is_rejected(): void
    {
        $this->withHeaders(['Idempotency-Key' => 'k', 'X-Analyst-Id' => $this->alice->id])
            ->postJson('/api/v1/lookups', ['indicator' => '8.8.8.8', 'provider' => 'nope'])
            ->assertStatus(422)->assertJsonPath('error.code', ApiErrorCode::ValidationFailed->value);
    }

    // ------------------------------------------------------------- run

    public function test_run_consumes_the_unit_and_returns_a_verdict(): void
    {
        $id = $this->request('8.8.8.8')->json('data.id');

        $this->runLookup($id)->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonStructure(['data' => ['result' => ['verdict', 'score', 'malware_families', 'looked_up_at']]]);
        $this->assertQuota(reserved: 0, consumed: 1);
    }

    public function test_running_twice_returns_the_same_result_without_a_second_unit(): void
    {
        $id = $this->request('8.8.8.8')->json('data.id');
        $first = $this->runLookup($id);

        $this->runLookup($id)->assertOk()->assertJsonPath('data.result', $first->json('data.result'));
        $this->assertQuota(reserved: 0, consumed: 1);
    }

    public function test_teammate_can_run_a_shared_lease(): void
    {
        $id = $this->request('8.8.8.8', as: $this->alice)->json('data.id');

        $this->runLookup($id, as: $this->bob)->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_other_team_cannot_run(): void
    {
        $id = $this->request('8.8.8.8')->json('data.id');
        $outsider = Analyst::factory()->create();

        $this->runLookup($id, as: $outsider)->assertForbidden()->assertJsonPath('error.code', ApiErrorCode::Forbidden->value);
    }

    public function test_queued_lookup_cannot_run(): void
    {
        $this->request('a.example.com');
        $this->request('b.example.com');
        $queued = $this->request('c.example.com')->json('data.id');

        $this->runLookup($queued)->assertStatus(409)->assertJsonPath('error.code', ApiErrorCode::LeaseNotActive->value);
    }

    public function test_run_after_expiry_fails_returns_the_unit_and_promotes_the_queue(): void
    {
        $expiring = $this->request('a.example.com')->json('data.id');
        $this->request('b.example.com');
        $waiting = $this->request('c.example.com')->json('data.id');

        $this->travel(301)->seconds();

        $this->runLookup($expiring)->assertStatus(409)->assertJsonPath('error.code', ApiErrorCode::LeaseExpired->value);
        $this->show($expiring)->assertJsonPath('data.status', 'expired');
        $this->show($waiting)->assertJsonPath('data.status', 'leased');
    }

    public function test_provider_failure_keeps_the_lease_for_a_retry(): void
    {
        $id = $this->request('8.8.8.8')->json('data.id');
        $this->failProvider();

        $this->runLookup($id)->assertStatus(503)->assertJsonPath('error.code', ApiErrorCode::ProviderUnavailable->value);
        $this->show($id)->assertJsonPath('data.status', 'leased');
        $this->assertQuota(reserved: 1, consumed: 0);
    }

    public function test_unknown_lookup_is_not_found(): void
    {
        $this->runLookup((string) Str::uuid7())->assertNotFound()->assertJsonPath('error.code', ApiErrorCode::NotFound->value);
        $this->show('not-a-uuid')->assertNotFound()->assertJsonPath('error.code', ApiErrorCode::NotFound->value);
    }

    // ------------------------------------------------------------- expiry & reads

    public function test_sweeper_expires_overdue_leases_and_promotes_fifo(): void
    {
        $this->request('a.example.com');
        $this->request('b.example.com');
        $first = $this->request('c.example.com')->json('data.id');
        $second = $this->request('d.example.com')->json('data.id');
        $third = $this->request('e.example.com')->json('data.id');

        $this->travel(301)->seconds();
        self::assertSame(2, $this->app->make(ExpireLeases::class)->expireDue());

        $this->show($first)->assertJsonPath('data.status', 'leased');
        $this->show($second)->assertJsonPath('data.status', 'leased');
        $this->show($third)->assertJsonPath('data.queue.position', 1);
        $this->assertQuota(reserved: 2, consumed: 0);
    }

    public function test_status_read_expires_lazily_without_the_sweeper(): void
    {
        $id = $this->request('8.8.8.8')->json('data.id');
        $this->travel(301)->seconds();

        $this->show($id)->assertJsonPath('data.status', 'expired')->assertJsonPath('data.lease', null);
        $this->assertQuota(reserved: 0, consumed: 0);
    }

    public function test_new_request_cannot_jump_the_queue_when_a_unit_frees_up(): void
    {
        $this->request('a.example.com');
        $this->request('b.example.com');
        $waiting = $this->request('c.example.com')->json('data.id');
        $this->travel(301)->seconds();

        $this->request('d.example.com')->assertJsonPath('data.status', 'leased');
        $this->show($waiting)->assertJsonPath('data.status', 'leased');
    }

    public function test_quota_dashboard(): void
    {
        $this->request('a.example.com');
        $this->runLookup($this->request('b.example.com')->json('data.id'));
        $this->request('c.example.com');

        $this->withHeaders(['X-Analyst-Id' => $this->alice->id])->getJson('/api/v1/quota')->assertOk()
            ->assertJsonPath('data.provider', 'mock-intel')
            ->assertJsonPath('data.limit', 2)
            ->assertJsonPath('data.reserved', 1)
            ->assertJsonPath('data.consumed', 1)
            ->assertJsonPath('data.remaining', 0)
            ->assertJsonPath('data.queued', 1)
            ->assertJsonPath('data.lease_ttl_seconds', 300);
    }

    public function test_recent_lists_newest_first_with_filters(): void
    {
        $this->request('a.example.com', as: $this->alice);
        $this->request('b.example.com', as: $this->bob);
        $this->request('c.example.com', as: $this->alice);

        $this->withHeaders(['X-Analyst-Id' => $this->alice->id])
            ->getJson('/api/v1/lookups?requested_by='.$this->alice->id)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.indicator.value', 'c.example.com');

        $this->withHeaders(['X-Analyst-Id' => $this->alice->id])
            ->getJson('/api/v1/lookups?status=queued')
            ->assertJsonCount(1, 'data');
    }

    // ------------------------------------------------------------- helpers

    /** @return TestResponse<Response> */
    private function request(string $indicator, ?Analyst $as = null, ?string $key = null): TestResponse
    {
        return $this->withHeaders([
            'Idempotency-Key' => $key ?? (string) Str::uuid(),
            'X-Analyst-Id' => ($as ?? $this->alice)->id,
        ])->postJson('/api/v1/lookups', ['indicator' => $indicator]);
    }

    /** @return TestResponse<Response> */
    private function runLookup(mixed $id, ?Analyst $as = null): TestResponse
    {
        return $this->withHeaders(['X-Analyst-Id' => ($as ?? $this->alice)->id])->postJson("/api/v1/lookups/{$this->str($id)}/run");
    }

    /** @return TestResponse<Response> */
    private function show(mixed $id): TestResponse
    {
        return $this->withHeaders(['X-Analyst-Id' => $this->alice->id])->getJson("/api/v1/lookups/{$this->str($id)}");
    }

    private function str(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }

    private function assertQuota(int $reserved, int $consumed): void
    {
        $quota = QuotaAllocation::query()->first();
        self::assertSame([$reserved, $consumed], [$quota->reserved ?? 0, $quota->consumed ?? 0], 'reserved/consumed');
    }

    private function failProvider(): void
    {
        $failing = new class implements ThreatIntelProvider
        {
            public function lookup(Indicator $indicator): ProviderResult
            {
                throw new DomainError(ApiErrorCode::ProviderUnavailable, 'Provider timed out.', ['Retry-After' => '5']);
            }
        };

        $this->app->forgetInstance(ProviderCatalog::class);
        $this->app->singleton(ProviderCatalog::class, fn (): ProviderCatalog => new ProviderCatalogService(
            $this->app->make(ProviderRepository::class),
            ['mock-intel' => $failing],
        ));
        foreach (['RunLookup', 'RequestEnrichment', 'LookupQuery'] as $contract) {
            $this->app->forgetInstance('App\\Modules\\Lookups\\Contracts\\'.$contract);
        }
        $this->app->forgetInstance(\App\Modules\Lookups\Application\LookupViewFactory::class);
    }
}
