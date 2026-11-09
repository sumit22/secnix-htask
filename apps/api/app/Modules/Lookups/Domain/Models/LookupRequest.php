<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Domain\Models;

use App\Modules\Lookups\Contracts\Enums\LookupStatus;
use App\Modules\Lookups\Database\Factories\LookupRequestFactory;
use App\Modules\Shared\Indicators\IndicatorType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A lookup request and its lease; lifecycle in LookupStatus.
 * provider_id, team_id, requested_by, quota_allocation_id reference other modules by ID only.
 *
 * @property string $id
 * @property string $provider_id
 * @property string $team_id
 * @property string|null $quota_allocation_id
 * @property string $requested_by
 * @property string $idempotency_key
 * @property string $request_hash
 * @property IndicatorType $indicator_type
 * @property string $indicator_value
 * @property LookupStatus $status
 * @property CarbonImmutable|null $leased_at
 * @property CarbonImmutable|null $lease_expires_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $expired_at
 * @property array<string, mixed>|null $result
 * @property CarbonImmutable $created_at
 * @property-read LookupQueueEntry|null $queueEntry
 */
#[Fillable([
    'provider_id', 'team_id', 'quota_allocation_id', 'requested_by', 'idempotency_key', 'request_hash',
    'indicator_type', 'indicator_value', 'status', 'leased_at', 'lease_expires_at', 'completed_at',
    'expired_at', 'result',
])]
#[UseFactory(LookupRequestFactory::class)]
final class LookupRequest extends Model
{
    /** @use HasFactory<LookupRequestFactory> */
    use HasFactory, HasUuids;

    /** @param Builder<self> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', LookupStatus::active());
    }

    /** @return HasOne<LookupQueueEntry, $this> */
    public function queueEntry(): HasOne
    {
        return $this->hasOne(LookupQueueEntry::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'indicator_type' => IndicatorType::class,
            'status' => LookupStatus::class,
            'leased_at' => 'immutable_datetime',
            'lease_expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'result' => 'array',
        ];
    }
}
