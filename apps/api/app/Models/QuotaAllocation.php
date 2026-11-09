<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\QuotaAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Units for one provider × scope × period. team_id NULL = org-level pool.
 * Invariant (DB CHECK): reserved + consumed <= quota_limit.
 *
 * @property string $id
 * @property string $provider_id
 * @property string|null $team_id
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property int $quota_limit
 * @property int $reserved
 * @property int $consumed
 * @property-read Provider $provider
 */
#[Fillable(['provider_id', 'team_id', 'period_start', 'period_end', 'quota_limit', 'reserved', 'consumed'])]
final class QuotaAllocation extends Model
{
    /** @use HasFactory<QuotaAllocationFactory> */
    use HasFactory, HasUuids;

    /**
     * Returns the org-level allocation for the UTC day containing $at, creating it if missing.
     * Race-safe: concurrent callers converge on the same row (ON CONFLICT DO NOTHING); an existing
     * allocation is never modified.
     */
    public static function ensureOrgAllocation(Provider $provider, CarbonImmutable $at, int $limit): self
    {
        $start = $at->utc()->startOfDay();

        self::query()->insertOrIgnore([
            'provider_id' => $provider->id,
            'team_id' => null,
            'period_start' => $start,
            'period_end' => $start->addDay(),
            'quota_limit' => $limit,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        return self::query()
            ->where('provider_id', $provider->id)
            ->whereNull('team_id')
            ->where('period_start', $start)
            ->sole();
    }

    public function remaining(): int
    {
        return $this->quota_limit - $this->reserved - $this->consumed;
    }

    /** @return BelongsTo<Provider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_datetime',
            'period_end' => 'immutable_datetime',
            'quota_limit' => 'integer',
            'reserved' => 'integer',
            'consumed' => 'integer',
        ];
    }
}
