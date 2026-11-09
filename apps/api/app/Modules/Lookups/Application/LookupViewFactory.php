<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Application;

use App\Modules\Lookups\Contracts\Enums\LookupStatus;
use App\Modules\Lookups\Contracts\LookupView;
use App\Modules\Lookups\Domain\Models\LookupRequest;
use App\Modules\Lookups\Domain\Repositories\WaitingQueue;
use App\Modules\Providers\Contracts\ProviderCatalog;
use App\Modules\Shared\Indicators\Indicator;
use Carbon\CarbonImmutable;

final readonly class LookupViewFactory
{
    public function __construct(
        private WaitingQueue $queue,
        private ProviderCatalog $providers,
    ) {}

    public function make(LookupRequest $lookup, bool $deduplicated, CarbonImmutable $now): LookupView
    {
        return new LookupView(
            id: $lookup->id,
            status: $lookup->status,
            indicator: new Indicator($lookup->indicator_type, $lookup->indicator_value),
            provider: $this->providers->findById($lookup->provider_id)->slug ?? 'unknown',
            requestedBy: $lookup->requested_by,
            deduplicated: $deduplicated,
            leasedAt: $lookup->leased_at,
            leaseExpiresAt: $lookup->lease_expires_at,
            queuePosition: $lookup->status === LookupStatus::Queued ? $this->queue->positionOf($lookup->id) : null,
            result: $lookup->result,
            createdAt: $lookup->created_at,
            completedAt: $lookup->completed_at,
            expiredAt: $lookup->expired_at,
            serverTime: $now,
        );
    }
}
