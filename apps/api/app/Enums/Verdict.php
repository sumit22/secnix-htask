<?php

declare(strict_types=1);

namespace App\Enums;

/** Reputation verdict returned by a provider. */
enum Verdict: string
{
    case Malicious = 'malicious';
    case Suspicious = 'suspicious';
    case Harmless = 'harmless';
    case Unknown = 'unknown';
}
