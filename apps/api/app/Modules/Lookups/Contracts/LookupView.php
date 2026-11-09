<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Contracts;

use App\Modules\Lookups\Contracts\Enums\LookupStatus;
use App\Modules\Shared\Indicators\Indicator;
use Carbon\CarbonImmutable;

/** Read model returned by every lookup endpoint (docs/api.md). */
final readonly class LookupView
{
    /** @param array<string, mixed>|null $result */
    public function __construct(
        public string $id,
        public LookupStatus $status,
        public Indicator $indicator,
        public string $provider,
        public string $requestedBy,
        public bool $deduplicated,
        public ?CarbonImmutable $leasedAt,
        public ?CarbonImmutable $leaseExpiresAt,
        public ?int $queuePosition,
        public ?array $result,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $completedAt,
        public ?CarbonImmutable $expiredAt,
        public CarbonImmutable $serverTime,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'indicator' => ['type' => $this->indicator->type->value, 'value' => $this->indicator->value],
            'provider' => $this->provider,
            'requested_by' => $this->requestedBy,
            'deduplicated' => $this->deduplicated,
            'lease' => $this->status === LookupStatus::Leased && $this->leasedAt !== null && $this->leaseExpiresAt !== null
                ? ['leased_at' => self::iso($this->leasedAt), 'expires_at' => self::iso($this->leaseExpiresAt)]
                : null,
            'queue' => $this->queuePosition === null ? null : ['position' => $this->queuePosition],
            'result' => $this->result,
            'created_at' => self::iso($this->createdAt),
            'completed_at' => self::iso($this->completedAt),
            'expired_at' => self::iso($this->expiredAt),
            'server_time' => self::iso($this->serverTime),
        ];
    }

    private static function iso(?CarbonImmutable $at): ?string
    {
        return $at?->utc()->toIso8601ZuluString();
    }
}
