<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a lookup request. See docs/hld.md §4.
 *
 * queued → leased → completed | expired. A request starts as leased (quota free) or queued (quota exhausted).
 */
enum LookupStatus: string
{
    case Queued = 'queued';
    case Leased = 'leased';
    case Completed = 'completed';
    case Expired = 'expired';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Queued => [self::Leased],
            self::Leased => [self::Completed, self::Expired],
            self::Completed, self::Expired => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** Statuses that occupy an indicator (at most one per indicator at a time). */
    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Queued, self::Leased];
    }
}
