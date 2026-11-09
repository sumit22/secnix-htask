<?php

declare(strict_types=1);

namespace App\Modules\Shared\Time;

use Carbon\CarbonImmutable;

final class SystemClock implements Clock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }
}
