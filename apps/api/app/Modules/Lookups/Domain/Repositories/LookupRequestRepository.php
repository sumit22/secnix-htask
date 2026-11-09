<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Domain\Repositories;

use App\Modules\Lookups\Contracts\Enums\LookupStatus;
use App\Modules\Lookups\Domain\Models\LookupRequest;
use App\Modules\Shared\Indicators\Indicator;
use Carbon\CarbonImmutable;

interface LookupRequestRepository
{
    public function find(string $id): ?LookupRequest;

    /** Row lock (FOR UPDATE) held until the transaction ends. */
    public function lockById(string $id): ?LookupRequest;

    public function findActive(string $providerId, Indicator $indicator): ?LookupRequest;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): LookupRequest;

    public function save(LookupRequest $lookup): void;

    /**
     * Overdue leases, locked with SKIP LOCKED so a lookup that is being run is never expired under it.
     *
     * @return list<LookupRequest>
     */
    public function lockDueLeases(string $providerId, CarbonImmutable $now): array;

    /** @return list<string> providers with overdue leases or waiting requests */
    public function providersNeedingMaintenance(CarbonImmutable $now): array;

    /** @return list<LookupRequest> newest first */
    public function recent(?string $requestedBy, ?LookupStatus $status, int $limit): array;
}
