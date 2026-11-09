<?php

declare(strict_types=1);

namespace App\Modules\Shared;

use App\Modules\Shared\Database\DatabaseTransactionRunner;
use App\Modules\Shared\Database\TransactionRunner;
use App\Modules\Shared\Time\Clock;
use App\Modules\Shared\Time\SystemClock;
use Illuminate\Support\ServiceProvider;

final class SharedServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        Clock::class => SystemClock::class,
        TransactionRunner::class => DatabaseTransactionRunner::class,
    ];
}
