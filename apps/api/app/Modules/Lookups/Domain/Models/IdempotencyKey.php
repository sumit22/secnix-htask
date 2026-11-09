<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $requested_by
 * @property string $idempotency_key
 * @property string $request_hash
 * @property string $lookup_request_id
 * @property bool $deduplicated
 * @property-read LookupRequest $lookupRequest
 */
#[Fillable(['requested_by', 'idempotency_key', 'request_hash', 'lookup_request_id', 'deduplicated'])]
final class IdempotencyKey extends Model
{
    use HasUuids;

    public const null UPDATED_AT = null;

    /** @return BelongsTo<LookupRequest, $this> */
    public function lookupRequest(): BelongsTo
    {
        return $this->belongsTo(LookupRequest::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['deduplicated' => 'boolean'];
    }
}
