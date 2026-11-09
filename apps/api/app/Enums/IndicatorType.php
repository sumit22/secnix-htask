<?php

declare(strict_types=1);

namespace App\Enums;

enum IndicatorType: string
{
    case Ip = 'ip';
    case Domain = 'domain';
    case Hash = 'hash';
}
