<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Application;

use App\Modules\Lookups\Contracts\ExpireLeases;
use App\Modules\Lookups\Domain\Repositories\LookupRequestRepository;
use App\Modules\Shared\Database\TransactionRunner;
use App\Modules\Shared\Time\Clock;

final readonly class ExpireLeasesService implements ExpireLeases
{
    public function __construct(
        private TransactionRunner $tx,
        private Clock $clock,
        private LookupRequestRepository $lookups,
        private LeaseManager $leases,
    ) {}

    public function expireDue(): int
    {
        $expired = 0;

        // One short transaction per provider keeps the quota lock brief.
        foreach ($this->lookups->providersNeedingMaintenance($this->clock->now()) as $providerId) {
            $expired += $this->tx->run(fn (): int => $this->leases->expireDueAndPromote($providerId, $this->clock->now()));
        }

        return $expired;
    }
}
