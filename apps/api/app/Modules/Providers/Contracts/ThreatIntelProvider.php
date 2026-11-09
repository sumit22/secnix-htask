<?php

declare(strict_types=1);

namespace App\Modules\Providers\Contracts;

use App\Modules\Shared\Exceptions\DomainError;
use App\Modules\Shared\Indicators\Indicator;

/** A third-party reputation lookup; each successful call spends one provider unit. */
interface ThreatIntelProvider
{
    /** @throws DomainError provider_unavailable when the call fails (no unit is consumed) */
    public function lookup(Indicator $indicator): ProviderResult;
}
