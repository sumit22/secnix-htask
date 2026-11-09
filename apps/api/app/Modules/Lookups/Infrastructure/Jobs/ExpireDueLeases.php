<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Infrastructure\Jobs;

use App\Modules\Lookups\Contracts\ExpireLeases;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Scheduled sweep; safe to run concurrently (SKIP LOCKED) and idempotent under at-least-once delivery. */
final class ExpireDueLeases implements ShouldQueue
{
    use Queueable;

    public function handle(ExpireLeases $leases): void
    {
        $leases->expireDue();
    }
}
