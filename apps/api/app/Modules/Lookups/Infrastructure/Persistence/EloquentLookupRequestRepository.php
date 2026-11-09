<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Infrastructure\Persistence;

use App\Modules\Lookups\Contracts\Enums\LookupStatus;
use App\Modules\Lookups\Domain\Models\LookupQueueEntry;
use App\Modules\Lookups\Domain\Models\LookupRequest;
use App\Modules\Lookups\Domain\Repositories\LookupRequestRepository;
use App\Modules\Shared\Indicators\Indicator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class EloquentLookupRequestRepository implements LookupRequestRepository
{
    public function find(string $id): ?LookupRequest
    {
        return Str::isUuid($id) ? LookupRequest::query()->find($id) : null;
    }

    public function lockById(string $id): ?LookupRequest
    {
        return LookupRequest::query()->lock('FOR NO KEY UPDATE')->find($id);
    }

    public function findActive(string $providerId, Indicator $indicator): ?LookupRequest
    {
        return LookupRequest::query()
            ->where('provider_id', $providerId)
            ->where('indicator_type', $indicator->type)
            ->where('indicator_value', $indicator->value)
            ->active()
            ->first();
    }

    public function create(array $attributes): LookupRequest
    {
        return LookupRequest::query()->create($attributes);
    }

    public function save(LookupRequest $lookup): void
    {
        $lookup->save();
    }

    public function lockDueLeases(string $providerId, CarbonImmutable $now): array
    {
        return array_values(LookupRequest::query()
            ->where('provider_id', $providerId)
            ->where('status', LookupStatus::Leased)
            ->where('lease_expires_at', '<=', $now)
            ->orderBy('lease_expires_at')
            ->lock('FOR NO KEY UPDATE SKIP LOCKED')
            ->get()
            ->all());
    }

    public function providersNeedingMaintenance(CarbonImmutable $now): array
    {
        $ids = LookupRequest::query()
            ->where('status', LookupStatus::Leased)
            ->where('lease_expires_at', '<=', $now)
            ->distinct()
            ->pluck('provider_id')
            ->merge(LookupQueueEntry::query()->distinct()->pluck('provider_id'));

        return array_values(array_unique(array_filter($ids->all(), is_string(...))));
    }

    public function recent(?string $requestedBy, ?LookupStatus $status, int $limit): array
    {
        $query = LookupRequest::query()->orderByDesc('created_at')->orderByDesc('id')->limit($limit);

        if ($requestedBy !== null) {
            $query->where('requested_by', $requestedBy);
        }
        if ($status !== null) {
            $query->where('status', $status);
        }

        return array_values($query->get()->all());
    }
}
