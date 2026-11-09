<?php

declare(strict_types=1);

namespace App\Modules\Quota\Domain\Models;

use App\Modules\Quota\Database\Factories\QuotaAllocationFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Units for one provider × scope × period; team_id NULL = org pool.
 * DB invariant: reserved + consumed <= quota_limit.
 *
 * @property string $id
 * @property string $provider_id
 * @property string|null $team_id
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property int $quota_limit
 * @property int $reserved
 * @property int $consumed
 */
#[Fillable(['provider_id', 'team_id', 'period_start', 'period_end', 'quota_limit', 'reserved', 'consumed'])]
#[UseFactory(QuotaAllocationFactory::class)]
final class QuotaAllocation extends Model
{
    /** @use HasFactory<QuotaAllocationFactory> */
    use HasFactory, HasUuids;

    public function remaining(): int
    {
        return $this->quota_limit - $this->reserved - $this->consumed;
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
