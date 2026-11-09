<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Application;

use App\Modules\Lookups\Contracts\Enums\LookupStatus;
use App\Modules\Lookups\Contracts\LookupQuery;
use App\Modules\Lookups\Contracts\LookupView;
use App\Modules\Lookups\Contracts\QuotaDashboard;
use App\Modules\Lookups\Domain\Models\LookupRequest;
use App\Modules\Lookups\Domain\Repositories\LookupRequestRepository;
use App\Modules\Lookups\Domain\Repositories\WaitingQueue;
use App\Modules\Providers\Contracts\ProviderCatalog;
use App\Modules\Quota\Contracts\QuotaLedger;
use App\Modules\Shared\Database\TransactionRunner;
use App\Modules\Shared\Enums\ApiErrorCode;
use App\Modules\Shared\Exceptions\DomainError;
use App\Modules\Shared\Time\Clock;

final readonly class LookupQueryService implements LookupQuery
{
    public function __construct(
        private TransactionRunner $tx,
        private Clock $clock,
        private LookupRequestRepository $lookups,
        private WaitingQueue $queue,
        private ProviderCatalog $providers,
        private QuotaLedger $quota,
        private LeaseManager $leases,
        private LookupViewFactory $views,
        private string $defaultProvider,
        private int $leaseTtlSeconds,
    ) {}

    public function find(string $lookupId): LookupView
    {
        $lookup = $this->lookups->find($lookupId)
            ?? throw new DomainError(ApiErrorCode::NotFound, 'Lookup not found.');

        $this->applyDueExpiries([$lookup]);

        return $this->views->make($lookup->refresh(), false, $this->clock->now());
    }

    public function recent(?string $requestedBy, ?LookupStatus $status, int $limit = 50): array
    {
        $lookups = $this->lookups->recent($requestedBy, $status, $limit);
        if ($this->applyDueExpiries($lookups)) {
            $lookups = $this->lookups->recent($requestedBy, $status, $limit);
        }

        $now = $this->clock->now();

        return array_map(fn (LookupRequest $l): LookupView => $this->views->make($l, false, $now), $lookups);
    }

    public function dashboard(?string $provider): QuotaDashboard
    {
        $ref = $this->providers->findBySlug($provider ?? $this->defaultProvider)
            ?? throw new DomainError(ApiErrorCode::ValidationFailed, 'Unknown provider.');

        $this->tx->run(fn (): int => $this->leases->expireDueAndPromote($ref->id, $this->clock->now()));
        $now = $this->clock->now();

        return new QuotaDashboard($ref->slug, $this->quota->snapshot($ref->id, $now), $this->queue->size($ref->id), $this->leaseTtlSeconds, $now);
    }

    /**
     * @param  list<LookupRequest>  $lookups
     * @return bool whether anything was expired
     */
    private function applyDueExpiries(array $lookups): bool
    {
        $now = $this->clock->now();
        $providers = [];
        foreach ($lookups as $lookup) {
            if ($lookup->status === LookupStatus::Leased && $lookup->lease_expires_at?->lessThanOrEqualTo($now) === true) {
                $providers[$lookup->provider_id] = true;
            }
        }

        foreach (array_keys($providers) as $providerId) {
            $this->tx->run(fn (): int => $this->leases->expireDueAndPromote((string) $providerId, $now));
        }

        return $providers !== [];
    }
}
