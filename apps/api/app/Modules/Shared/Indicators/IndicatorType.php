<?php

declare(strict_types=1);

namespace App\Modules\Shared\Indicators;

enum IndicatorType: string
{
    case Ip = 'ip';
    case Domain = 'domain';
    case Hash = 'hash';
}
