<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Contracts;

use App\Modules\Identity\Contracts\Requester;
use App\Modules\Shared\Indicators\IndicatorType;

final readonly class EnrichmentRequest
{
    public function __construct(
        public Requester $requester,
        public string $idempotencyKey,
        public string $indicator,
        public ?IndicatorType $indicatorType = null,
        public ?string $provider = null,
    ) {}
}
