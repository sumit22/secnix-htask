<?php

declare(strict_types=1);

namespace App\Modules\Shared\Time;

use Carbon\CarbonImmutable;

/** Injected time source, so lease expiry is testable with a frozen clock. */
interface Clock
{
    public function now(): CarbonImmutable;
}
