<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The database is the last line of defence for the business rules (docs/hld.md §3).
 * These tests bypass the application and hit the tables directly.
 */
final class SchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private const string CHECK_VIOLATION = '23514';

    private const string UNIQUE_VIOLATION = '23505';

    public function test_primary_keys_default_to_uuid_v7(): void
    {
        $teamId = $this->team();

        self::assertTrue(Str::isUuid($teamId));
        self::assertSame('7', $teamId[14], 'version nibble must be 7');
    }

    public function test_quota_cannot_be_over_allocated(): void
    {
        $allocationId = $this->allocation(limit: 10, reserved: 6, consumed: 4);

        $this->assertViolates(self::CHECK_VIOLATION, fn () => DB::table('quota_allocations')
            ->where('id', $allocationId)->increment('reserved'));
    }

    public function test_quota_counters_cannot_go_negative(): void
    {
        $allocationId = $this->allocation(limit: 10);

        $this->assertViolates(self::CHECK_VIOLATION, fn () => DB::table('quota_allocations')
            ->where('id', $allocationId)->decrement('reserved'));
    }

    public function test_one_org_level_allocation_per_provider_and_period(): void
    {
        $providerId = $this->provider();
        $this->allocation(providerId: $providerId);

        // team_id is NULL for both rows: NULLS NOT DISTINCT must still treat them as duplicates.
        $this->assertViolates(self::UNIQUE_VIOLATION, fn () => $this->allocation(providerId: $providerId));
    }

    public function test_idempotency_key_is_unique_per_requester(): void
    {
        $userId = $this->user();
        $this->lookup(userId: $userId, idempotencyKey: 'retry-1', indicator: 'a.example.com');

        $this->assertViolates(self::UNIQUE_VIOLATION, fn () => $this->lookup(
            userId: $userId, idempotencyKey: 'retry-1', indicator: 'b.example.com',
        ));
    }

    public function test_same_idempotency_key_is_allowed_for_different_requesters(): void
    {
        $this->lookup(userId: $this->user(), idempotencyKey: 'shared', indicator: 'a.example.com');
        $this->lookup(userId: $this->user(), idempotencyKey: 'shared', indicator: 'b.example.com');

        self::assertSame(2, DB::table('lookup_requests')->count());
    }

    public function test_an_indicator_can_have_only_one_active_lookup(): void
    {
        $providerId = $this->provider();
        $this->lookup(providerId: $providerId, status: 'leased', indicator: 'evil.example.com');

        $this->assertViolates(self::UNIQUE_VIOLATION, fn () => $this->lookup(
            providerId: $providerId, status: 'queued', indicator: 'evil.example.com',
        ));
    }

    public function test_finished_lookups_do_not_block_a_new_active_one(): void
    {
        $providerId = $this->provider();
        $this->lookup(providerId: $providerId, status: 'completed', indicator: 'evil.example.com');
        $this->lookup(providerId: $providerId, status: 'expired', indicator: 'evil.example.com');
        $this->lookup(providerId: $providerId, status: 'leased', indicator: 'evil.example.com');

        self::assertSame(3, DB::table('lookup_requests')->count());
    }

    public function test_status_and_indicator_type_are_constrained(): void
    {
        $this->assertViolates(self::CHECK_VIOLATION, fn () => $this->lookup(status: 'running'));
        $this->assertViolates(self::CHECK_VIOLATION, fn () => $this->lookup(indicatorType: 'url'));
    }

    public function test_a_leased_lookup_requires_an_expiry(): void
    {
        $this->assertViolates(self::CHECK_VIOLATION, fn () => $this->lookup(status: 'leased', leaseExpiresAt: null));
    }

    public function test_user_role_is_constrained(): void
    {
        $this->assertViolates(self::CHECK_VIOLATION, fn () => $this->user(role: 'superuser'));
    }

    public function test_queue_ids_increase_in_arrival_order(): void
    {
        $providerId = $this->provider();
        $first = $this->enqueue($providerId, $this->lookup(providerId: $providerId, status: 'queued', indicator: 'a.example.com'));
        $second = $this->enqueue($providerId, $this->lookup(providerId: $providerId, status: 'queued', indicator: 'b.example.com'));

        self::assertGreaterThan($first, $second);
    }

    public function test_a_lookup_can_be_queued_only_once(): void
    {
        $providerId = $this->provider();
        $lookupId = $this->lookup(providerId: $providerId, status: 'queued');
        $this->enqueue($providerId, $lookupId);

        $this->assertViolates(self::UNIQUE_VIOLATION, fn () => $this->enqueue($providerId, $lookupId));
    }

    // ---------------------------------------------------------------- helpers

    /** @param callable(): mixed $statement */
    private function assertViolates(string $sqlState, callable $statement): void
    {
        try {
            DB::transaction(static function () use ($statement): void {
                $statement();
            });
        } catch (QueryException $e) {
            self::assertSame($sqlState, $e->getCode(), $e->getMessage());

            return;
        }

        self::fail("Expected SQLSTATE {$sqlState}, but the statement succeeded.");
    }

    private function team(): string
    {
        return $this->insertReturningId('teams', ['slug' => 'team-'.Str::random(8), 'name' => 'Team']);
    }

    private function user(string $role = 'analyst'): string
    {
        return $this->insertReturningId('users', [
            'name' => 'Analyst',
            'email' => Str::random(10).'@example.com',
            'password' => 'not-used',
            'role' => $role,
            'team_id' => $this->team(),
        ]);
    }

    private function provider(): string
    {
        return $this->insertReturningId('providers', ['slug' => 'provider-'.Str::random(8), 'name' => 'Provider']);
    }

    private function allocation(?string $providerId = null, int $limit = 10, int $reserved = 0, int $consumed = 0): string
    {
        return $this->insertReturningId('quota_allocations', [
            'provider_id' => $providerId ?? $this->provider(),
            'team_id' => null,
            'period_start' => '2026-11-09 00:00:00+00',
            'period_end' => '2026-11-10 00:00:00+00',
            'quota_limit' => $limit,
            'reserved' => $reserved,
            'consumed' => $consumed,
        ]);
    }

    private function lookup(
        ?string $providerId = null,
        ?string $userId = null,
        string $status = 'completed',
        string $indicator = 'example.com',
        string $indicatorType = 'domain',
        ?string $idempotencyKey = null,
        ?string $leaseExpiresAt = '2026-11-09 00:05:00+00',
    ): string {
        $userId ??= $this->user();

        return $this->insertReturningId('lookup_requests', [
            'provider_id' => $providerId ?? $this->provider(),
            'team_id' => DB::table('users')->where('id', $userId)->value('team_id'),
            'requested_by' => $userId,
            'idempotency_key' => $idempotencyKey ?? Str::uuid()->toString(),
            'request_hash' => hash('sha256', $indicator),
            'indicator_type' => $indicatorType,
            'indicator_value' => $indicator,
            'status' => $status,
            'lease_expires_at' => $leaseExpiresAt,
        ]);
    }

    private function enqueue(string $providerId, string $lookupId): int
    {
        $id = DB::table('lookup_queue')->insertGetId([
            'provider_id' => $providerId,
            'lookup_request_id' => $lookupId,
        ]);

        return (int) $id;
    }

    /** @param array<string, mixed> $values */
    private function insertReturningId(string $table, array $values): string
    {
        $id = DB::table($table)->insertGetId($values);

        self::assertIsString($id);

        return $id;
    }
}
