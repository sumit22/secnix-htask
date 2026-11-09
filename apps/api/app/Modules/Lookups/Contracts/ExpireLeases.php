<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Contracts;

/** Expire overdue leases, return their units and promote queued requests FIFO. */
interface ExpireLeases
{
    /** @return int leases expired */
    public function expireDue(): int;
}
