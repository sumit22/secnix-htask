<?php

declare(strict_types=1);

namespace Tests\Unit\Contracts;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\AssertsContract;

/** The contract check must reject drift, not just accept valid payloads. */
final class ContractSchemasTest extends TestCase
{
    use AssertsContract;

    /** @return array<string, mixed> */
    private static function queued(): array
    {
        return [
            'id' => '01a1c02f-0000-7000-8000-000000000001', 'status' => 'queued',
            'indicator' => ['type' => 'domain', 'value' => 'evil.example.com'],
            'provider' => 'mock-intel', 'requested_by' => '01a1c02f-0000-7000-8000-0000000000a1',
            'deduplicated' => false, 'lease' => null, 'queue' => ['position' => 1], 'result' => null,
            'created_at' => '2026-11-09T10:00:00Z', 'completed_at' => null, 'expired_at' => null,
            'server_time' => '2026-11-09T10:00:00Z',
        ];
    }

    public function test_accepts_a_conformant_lookup(): void
    {
        $this->assertMatchesContract('lookup-view.schema.json', self::queued());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function drifted(): iterable
    {
        yield 'renamed field' => [['queue_position' => 1] + array_diff_key(self::queued(), ['queue' => 0])];
        yield 'unknown status' => [['status' => 'running'] + self::queued()];
        yield 'queued without position' => [['queue' => null] + self::queued()];
        yield 'lease on a queued lookup' => [['lease' => ['leased_at' => '2026-11-09T10:00:00Z', 'expires_at' => '2026-11-09T10:05:00Z']] + self::queued()];
    }

    /** @param array<string, mixed> $payload */
    #[\PHPUnit\Framework\Attributes\DataProvider('drifted')]
    public function test_rejects_drift(array $payload): void
    {
        $this->expectException(AssertionFailedError::class);

        $this->assertMatchesContract('lookup-view.schema.json', $payload);
    }
}
