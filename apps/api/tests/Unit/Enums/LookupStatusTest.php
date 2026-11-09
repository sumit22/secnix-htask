<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\LookupStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LookupStatusTest extends TestCase
{
    /** @return iterable<string, array{LookupStatus, LookupStatus, bool}> */
    public static function transitions(): iterable
    {
        yield 'queued → leased (promotion)' => [LookupStatus::Queued, LookupStatus::Leased, true];
        yield 'leased → completed (run)' => [LookupStatus::Leased, LookupStatus::Completed, true];
        yield 'leased → expired (ttl)' => [LookupStatus::Leased, LookupStatus::Expired, true];
        yield 'queued → completed is not allowed' => [LookupStatus::Queued, LookupStatus::Completed, false];
        yield 'queued → expired is not allowed' => [LookupStatus::Queued, LookupStatus::Expired, false];
        yield 'leased → queued is not allowed' => [LookupStatus::Leased, LookupStatus::Queued, false];
        yield 'completed is terminal' => [LookupStatus::Completed, LookupStatus::Leased, false];
        yield 'expired is terminal' => [LookupStatus::Expired, LookupStatus::Leased, false];
        yield 'no self transition' => [LookupStatus::Leased, LookupStatus::Leased, false];
    }

    #[DataProvider('transitions')]
    public function test_transition_rules(LookupStatus $from, LookupStatus $to, bool $allowed): void
    {
        self::assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_active_statuses_are_queued_and_leased(): void
    {
        self::assertSame([LookupStatus::Queued, LookupStatus::Leased], LookupStatus::active());
        self::assertTrue(LookupStatus::Queued->isActive());
        self::assertTrue(LookupStatus::Leased->isActive());
        self::assertFalse(LookupStatus::Completed->isActive());
        self::assertFalse(LookupStatus::Expired->isActive());
    }

    public function test_terminal_statuses_have_no_transitions(): void
    {
        self::assertTrue(LookupStatus::Completed->isTerminal());
        self::assertTrue(LookupStatus::Expired->isTerminal());
        self::assertFalse(LookupStatus::Queued->isTerminal());
        self::assertFalse(LookupStatus::Leased->isTerminal());
    }
}
