<?php

declare(strict_types=1);

namespace App\Modules\Quota;

use App\Modules\Quota\Domain\Repositories\QuotaAllocationRepository;
use App\Modules\Quota\Infrastructure\Persistence\EloquentQuotaAllocationRepository;
use Illuminate\Support\ServiceProvider;

final class QuotaServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        QuotaAllocationRepository::class => EloquentQuotaAllocationRepository::class,
    ];
}
