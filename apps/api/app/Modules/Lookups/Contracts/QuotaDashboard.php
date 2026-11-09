<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Contracts;

use App\Modules\Quota\Contracts\QuotaSnapshot;
use Carbon\CarbonImmutable;

final readonly class QuotaDashboard
{
    public function __construct(
        public string $provider,
        public QuotaSnapshot $quota,
        public int $queued,
        public int $leaseTtlSeconds,
        public CarbonImmutable $serverTime,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'period' => [
                'start' => $this->quota->periodStart->utc()->toIso8601ZuluString(),
                'end' => $this->quota->periodEnd->utc()->toIso8601ZuluString(),
            ],
            'limit' => $this->quota->limit,
            'reserved' => $this->quota->reserved,
            'consumed' => $this->quota->consumed,
            'remaining' => $this->quota->remaining(),
            'queued' => $this->queued,
            'lease_ttl_seconds' => $this->leaseTtlSeconds,
            'server_time' => $this->serverTime->utc()->toIso8601ZuluString(),
        ];
    }
}
