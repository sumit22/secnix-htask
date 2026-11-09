<?php

declare(strict_types=1);

namespace App\Modules\Providers\Contracts\Enums;

/** Reputation verdict returned by a provider. */
enum Verdict: string
{
    case Malicious = 'malicious';
    case Suspicious = 'suspicious';
    case Harmless = 'harmless';
    case Unknown = 'unknown';
}
