<?php

declare(strict_types=1);

namespace App\Modules\Quota;

use App\Modules\Quota\Application\QuotaLedgerService;
use App\Modules\Quota\Contracts\QuotaLedger;
use App\Modules\Quota\Domain\Repositories\QuotaAllocationRepository;
use App\Modules\Quota\Infrastructure\Persistence\EloquentQuotaAllocationRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class QuotaServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        QuotaAllocationRepository::class => EloquentQuotaAllocationRepository::class,
    ];

    public function register(): void
    {
        $this->app->singleton(QuotaLedger::class, static fn (Application $app): QuotaLedger => new QuotaLedgerService(
            $app->make(QuotaAllocationRepository::class),
            $app->make('config')->integer('enrichment.default_daily_quota'),
        ));
    }
}
