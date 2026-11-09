<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Application;

use App\Modules\Lookups\Contracts\Enums\LookupStatus;
use App\Modules\Lookups\Domain\Models\LookupRequest;
use App\Modules\Lookups\Domain\Repositories\LookupRequestRepository;
use App\Modules\Lookups\Domain\Repositories\WaitingQueue;
use App\Modules\Quota\Contracts\QuotaLedger;
use App\Modules\Quota\Contracts\QuotaSnapshot;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Lease transitions shared by the use cases. Every method must run inside a transaction;
 * lock order is always quota row → lookup rows.
 */
final readonly class LeaseManager
{
    public function __construct(
        private QuotaLedger $quota,
        private LookupRequestRepository $lookups,
        private WaitingQueue $queue,
        private int $leaseTtlSeconds,
    ) {}

    /** Expire overdue leases (returning their units), then promote waiting requests FIFO. */
    public function expireDueAndPromote(string $providerId, CarbonImmutable $now): int
    {
        $this->quota->lock($providerId, $now);

        $expired = $this->lookups->lockDueLeases($providerId, $now);
        foreach ($expired as $lease) {
            $this->expire($lease, $now);
        }

        $this->promote($providerId, $now);

        return count($expired);
    }

    /** Caller holds the quota lock. */
    public function expire(LookupRequest $lease, CarbonImmutable $now): void
    {
        $this->transition($lease, LookupStatus::Expired);
        $lease->expired_at = $now;
        $this->lookups->save($lease);

        if ($lease->quota_allocation_id !== null) {
            $this->quota->release($lease->quota_allocation_id);
        }
    }

    /** Give every free unit to the oldest waiting request. */
    public function promote(string $providerId, CarbonImmutable $now): void
    {
        $quota = $this->quota->lock($providerId, $now);
        $free = $quota->remaining();

        while ($free > 0 && ($lookupId = $this->queue->claimHead($providerId)) !== null) {
            $lookup = $this->lookups->lockById($lookupId);
            if ($lookup === null || $lookup->status !== LookupStatus::Queued) {
                continue;
            }

            $this->lease($lookup, $quota, $now);
            $free--;
        }
    }

    /**
     * Caller holds the quota lock and has checked a unit is free.
     * The lease ends at the allocation's period end at the latest, so it never overlaps the next day's fresh quota.
     */
    public function lease(LookupRequest $lookup, QuotaSnapshot $quota, CarbonImmutable $now): void
    {
        $this->quota->reserve($quota->allocationId);

        $this->transition($lookup, LookupStatus::Leased);
        $lookup->quota_allocation_id = $quota->allocationId;
        $lookup->leased_at = $now;
        $lookup->lease_expires_at = $now->addSeconds($this->leaseTtlSeconds)->min($quota->periodEnd);
        $this->lookups->save($lookup);
    }

    private function transition(LookupRequest $lookup, LookupStatus $to): void
    {
        if (! $lookup->status->canTransitionTo($to)) {
            throw new LogicException("Illegal lookup transition {$lookup->status->value} → {$to->value}.");
        }
        $lookup->status = $to;
    }
}
