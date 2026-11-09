<?php

declare(strict_types=1);

use App\Modules\Quota\QuotaServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    QuotaServiceProvider::class,
];
