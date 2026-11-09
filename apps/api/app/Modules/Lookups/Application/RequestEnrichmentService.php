<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Application;

use App\Modules\Lookups\Contracts\EnrichmentOutcome;
use App\Modules\Lookups\Contracts\EnrichmentRequest;
use App\Modules\Lookups\Contracts\Enums\LookupStatus;
use App\Modules\Lookups\Contracts\RequestEnrichment;
use App\Modules\Lookups\Domain\Repositories\IdempotencyKeyRepository;
use App\Modules\Lookups\Domain\Repositories\LookupRequestRepository;
use App\Modules\Lookups\Domain\Repositories\WaitingQueue;
use App\Modules\Providers\Contracts\ProviderCatalog;
use App\Modules\Providers\Contracts\ProviderRef;
use App\Modules\Quota\Contracts\QuotaLedger;
use App\Modules\Shared\Database\TransactionRunner;
use App\Modules\Shared\Enums\ApiErrorCode;
use App\Modules\Shared\Exceptions\DomainError;
use App\Modules\Shared\Indicators\Indicator;
use App\Modules\Shared\Indicators\IndicatorNormalizer;
use App\Modules\Shared\Time\Clock;

final readonly class RequestEnrichmentService implements RequestEnrichment
{
    public function __construct(
        private TransactionRunner $tx,
        private Clock $clock,
        private IndicatorNormalizer $normalizer,
        private ProviderCatalog $providers,
        private QuotaLedger $quota,
        private LookupRequestRepository $lookups,
        private IdempotencyKeyRepository $keys,
        private WaitingQueue $queue,
        private LeaseManager $leases,
        private LookupViewFactory $views,
        private string $defaultProvider,
    ) {}

    public function request(EnrichmentRequest $request): EnrichmentOutcome
    {
        $indicator = $this->normalizer->normalize($request->indicator, $request->indicatorType);
        $provider = $this->resolveProvider($request->provider);
        $hash = self::fingerprint($provider, $indicator);

        return $this->tx->run(function () use ($request, $indicator, $provider, $hash): EnrichmentOutcome {
            $now = $this->clock->now();
            $requesterId = $request->requester->id;

            // Serialises every decision below for this provider; frees overdue units first so FIFO is respected.
            $this->leases->expireDueAndPromote($provider->id, $now);
            $quota = $this->quota->lock($provider->id, $now);

            $previous = $this->keys->find($requesterId, $request->idempotencyKey);
            if ($previous !== null) {
                if (! hash_equals($previous->request_hash, $hash)) {
                    throw new DomainError(ApiErrorCode::IdempotencyKeyReused, 'This Idempotency-Key was already used with a different request.');
                }

                return new EnrichmentOutcome($this->views->make($previous->lookupRequest, $previous->deduplicated, $now), created: false, replayed: true);
            }

            $active = $this->lookups->findActive($provider->id, $indicator);
            if ($active !== null) {
                $this->keys->record($requesterId, $request->idempotencyKey, $hash, $active->id, deduplicated: true);

                return new EnrichmentOutcome($this->views->make($active, true, $now), created: false, replayed: false);
            }

            $lookup = $this->lookups->create([
                'provider_id' => $provider->id,
                'team_id' => $request->requester->teamId,
                'requested_by' => $requesterId,
                'indicator_type' => $indicator->type,
                'indicator_value' => $indicator->value,
                'status' => LookupStatus::Queued,
            ]);

            // A free unit goes to the queue head first; a newcomer leases only when nobody is waiting.
            if ($quota->remaining() > 0 && $this->queue->size($provider->id) === 0) {
                $this->leases->lease($lookup, $quota->allocationId, $now);
            } else {
                $this->queue->enqueue($provider->id, $lookup->id);
            }

            $this->keys->record($requesterId, $request->idempotencyKey, $hash, $lookup->id, deduplicated: false);

            return new EnrichmentOutcome($this->views->make($lookup->refresh(), false, $now), created: true, replayed: false);
        });
    }

    private function resolveProvider(?string $slug): ProviderRef
    {
        return $this->providers->findBySlug($slug ?? $this->defaultProvider)
            ?? throw new DomainError(ApiErrorCode::ValidationFailed, 'Unknown provider.');
    }

    /** Same normalised request → same hash, so "Example.COM" and "example.com" retries match. */
    private static function fingerprint(ProviderRef $provider, Indicator $indicator): string
    {
        return hash('sha256', implode('|', [$provider->slug, $indicator->type->value, $indicator->value]));
    }
}
