<?php

declare(strict_types=1);

namespace App\Modules\Shared\Indicators;

final readonly class Indicator
{
    public function __construct(
        public IndicatorType $type,
        public string $value,
    ) {}
}
