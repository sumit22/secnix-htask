<?php

declare(strict_types=1);

use App\Modules\Quota\QuotaServiceProvider;
use App\Modules\Shared\SharedServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    QuotaServiceProvider::class,
];
