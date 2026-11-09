<?php

declare(strict_types=1);

use App\Modules\Lookups\Infrastructure\Jobs\ExpireDueLeases;
use Illuminate\Support\Facades\Schedule;

// Reads also expire lazily; the sweep returns units and promotes the queue even when nobody is polling.
Schedule::job(new ExpireDueLeases)->everyTenSeconds();
