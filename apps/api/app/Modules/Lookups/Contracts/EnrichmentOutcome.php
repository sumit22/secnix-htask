<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Contracts;

final readonly class EnrichmentOutcome
{
    public function __construct(
        public LookupView $view,
        public bool $created,
        public bool $replayed,
    ) {}
}
