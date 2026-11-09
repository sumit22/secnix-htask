<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Application;

use App\Modules\Identity\Contracts\Requester;
use App\Modules\Lookups\Contracts\Enums\LookupStatus;
use App\Modules\Lookups\Contracts\LookupView;
use App\Modules\Lookups\Contracts\RunLookup;
use App\Modules\Lookups\Domain\Models\LookupRequest;
use App\Modules\Lookups\Domain\Repositories\LookupRequestRepository;
use App\Modules\Providers\Contracts\ProviderCatalog;
use App\Modules\Quota\Contracts\QuotaLedger;
use App\Modules\Shared\Database\TransactionRunner;
use App\Modules\Shared\Enums\ApiErrorCode;
use App\Modules\Shared\Exceptions\DomainError;
use App\Modules\Shared\Indicators\Indicator;
use App\Modules\Shared\Time\Clock;
use LogicException;
use Throwable;

final readonly class RunLookupService implements RunLookup
{
    public function __construct(
        private TransactionRunner $tx,
        private Clock $clock,
        private LookupRequestRepository $lookups,
        private ProviderCatalog $providers,
        private QuotaLedger $quota,
        private LeaseManager $leases,
        private LookupViewFactory $views,
    ) {}

    public function run(string $lookupId, Requester $requester): LookupView
    {
        $lookup = $this->lookups->find($lookupId)
            ?? throw new DomainError(ApiErrorCode::NotFound, 'Lookup not found.');

        if ($lookup->team_id !== $requester->teamId) {
            throw new DomainError(ApiErrorCode::Forbidden, 'Only members of the requesting team can run this lookup.');
        }

        // Expiry must commit even though the caller gets an error, so the error is raised after the transaction.
        [$lookup, $error] = $this->tx->run(fn (): array => $this->runLocked($lookupId));

        if ($error !== null) {
            throw $error;
        }

        return $this->views->make($lookup, false, $this->clock->now());
    }

    /** @return array{LookupRequest, DomainError|null} */
    private function runLocked(string $lookupId): array
    {
        // The row lock is held across the provider call: concurrent runs wait, and the sweeper (SKIP LOCKED) cannot expire it.
        $lookup = $this->lookups->lockById($lookupId) ?? throw new LogicException('Lookup vanished.');
        $now = $this->clock->now();

        switch ($lookup->status) {
            case LookupStatus::Completed:
                return [$lookup, null];
            case LookupStatus::Queued:
                return [$lookup, new DomainError(ApiErrorCode::LeaseNotActive, 'This lookup is still queued for quota.')];
            case LookupStatus::Expired:
                return [$lookup, new DomainError(ApiErrorCode::LeaseExpired, 'The lease expired; request the lookup again.')];
            case LookupStatus::Leased:
                break;
        }

        if ($lookup->lease_expires_at === null || $lookup->lease_expires_at->lessThanOrEqualTo($now)) {
            $this->quota->lock($lookup->provider_id, $now);
            $this->leases->expire($lookup, $now);
            $this->leases->promote($lookup->provider_id, $now);

            return [$lookup, new DomainError(ApiErrorCode::LeaseExpired, 'The lease expired; request the lookup again.')];
        }

        $provider = $this->providers->findById($lookup->provider_id) ?? throw new LogicException('Provider vanished.');
        try {
            $result = $this->providers->client($provider)
                ->lookup(new Indicator($lookup->indicator_type, $lookup->indicator_value));
        } catch (DomainError $e) {
            throw $e;
        } catch (Throwable $e) {
            // Any provider fault rolls back with the lease intact; the analyst can retry within the TTL.
            report($e);

            throw new DomainError(ApiErrorCode::ProviderUnavailable, 'The provider is unavailable; retry shortly.', ['Retry-After' => '5']);
        }

        if ($lookup->quota_allocation_id === null) {
            throw new LogicException('Leased lookup without an allocation.');
        }
        $this->quota->consume($lookup->quota_allocation_id);

        $lookup->status = LookupStatus::Completed;
        $lookup->completed_at = $this->clock->now();
        $lookup->result = $result->toArray();
        $this->lookups->save($lookup);

        // Return what is stored (jsonb normalises key order) so every read of the result is identical.
        return [$lookup->refresh(), null];
    }
}
