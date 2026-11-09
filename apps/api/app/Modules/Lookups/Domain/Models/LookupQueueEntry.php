<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A waiting request. `id` (bigint identity) is the FIFO order and is never exposed.
 *
 * @property int $id
 * @property string $provider_id
 * @property string $lookup_request_id
 * @property CarbonImmutable $enqueued_at
 * @property-read LookupRequest $lookupRequest
 */
#[Fillable(['provider_id', 'lookup_request_id'])]
final class LookupQueueEntry extends Model
{
    public $timestamps = false;

    protected $table = 'lookup_queue';

    /** @return BelongsTo<LookupRequest, $this> */
    public function lookupRequest(): BelongsTo
    {
        return $this->belongsTo(LookupRequest::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enqueued_at' => 'immutable_datetime'];
    }
}
