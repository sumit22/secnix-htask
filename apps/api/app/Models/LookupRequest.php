<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IndicatorType;
use App\Enums\LookupStatus;
use Carbon\CarbonImmutable;
use Database\Factories\LookupRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A lookup request and its lease. State machine: App\Enums\LookupStatus (docs/hld.md §4).
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
 * @property-read Provider $provider
 * @property-read Team $team
 * @property-read User $requester
 * @property-read QuotaAllocation|null $quotaAllocation
 * @property-read LookupQueueEntry|null $queueEntry
 */
#[Fillable([
    'provider_id', 'team_id', 'quota_allocation_id', 'requested_by', 'idempotency_key', 'request_hash',
    'indicator_type', 'indicator_value', 'status', 'leased_at', 'lease_expires_at', 'completed_at',
    'expired_at', 'result',
])]
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

    /** @return BelongsTo<Provider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<QuotaAllocation, $this> */
    public function quotaAllocation(): BelongsTo
    {
        return $this->belongsTo(QuotaAllocation::class);
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
