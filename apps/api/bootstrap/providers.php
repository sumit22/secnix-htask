<?php

declare(strict_types=1);

use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Lookups\LookupsServiceProvider;
use App\Modules\Providers\ProvidersServiceProvider;
use App\Modules\Quota\QuotaServiceProvider;
use App\Modules\Shared\SharedServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    IdentityServiceProvider::class,
    ProvidersServiceProvider::class,
    QuotaServiceProvider::class,
    LookupsServiceProvider::class,
];
