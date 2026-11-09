<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Contracts;

/** Reserve a lease (or queue) for an indicator; idempotent per (requester, key); dedups active indicators. */
interface RequestEnrichment
{
    public function request(EnrichmentRequest $request): EnrichmentOutcome;
}
