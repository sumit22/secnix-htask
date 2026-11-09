<?php

declare(strict_types=1);

namespace App\Modules\Providers\Contracts;

use App\Modules\Providers\Contracts\Enums\Verdict;
use Carbon\CarbonImmutable;

final readonly class ProviderResult
{
    /** @param list<string> $malwareFamilies */
    public function __construct(
        public Verdict $verdict,
        public int $score,
        public array $malwareFamilies,
        public CarbonImmutable $lookedUpAt,
    ) {}

    /** @return array{verdict: string, score: int, malware_families: list<string>, looked_up_at: string} */
    public function toArray(): array
    {
        return [
            'verdict' => $this->verdict->value,
            'score' => $this->score,
            'malware_families' => $this->malwareFamilies,
            'looked_up_at' => $this->lookedUpAt->toIso8601ZuluString(),
        ];
    }
}
