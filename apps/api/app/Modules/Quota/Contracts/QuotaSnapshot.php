<?php

declare(strict_types=1);

namespace App\Modules\Quota\Contracts;

use Carbon\CarbonImmutable;

final readonly class QuotaSnapshot
{
    public function __construct(
        public string $allocationId,
        public int $limit,
        public int $reserved,
        public int $consumed,
        public CarbonImmutable $periodStart,
        public CarbonImmutable $periodEnd,
    ) {}

    public function remaining(): int
    {
        return $this->limit - $this->reserved - $this->consumed;
    }
}
