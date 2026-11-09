<?php

declare(strict_types=1);

namespace App\Support\Indicators;

use App\Enums\IndicatorType;

final readonly class NormalizedIndicator
{
    public function __construct(
        public IndicatorType $type,
        public string $value,
    ) {}
}
