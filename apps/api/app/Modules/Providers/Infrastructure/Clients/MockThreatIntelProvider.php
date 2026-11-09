<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Clients;

use App\Modules\Providers\Contracts\Enums\Verdict;
use App\Modules\Providers\Contracts\ProviderResult;
use App\Modules\Providers\Contracts\ThreatIntelProvider;
use App\Modules\Shared\Indicators\Indicator;
use App\Modules\Shared\Time\Clock;

/** Stand-in for a real provider: random verdict, no API key. */
final readonly class MockThreatIntelProvider implements ThreatIntelProvider
{
    public const string SLUG = 'mock-intel';

    private const array FAMILIES = ['Emotet', 'TrickBot', 'QakBot', 'AgentTesla', 'Cobalt Strike'];

    public function __construct(private Clock $clock) {}

    public function lookup(Indicator $indicator): ProviderResult
    {
        $verdicts = Verdict::cases();
        $verdict = $verdicts[random_int(0, count($verdicts) - 1)];

        [$score, $families] = match ($verdict) {
            Verdict::Malicious => [random_int(70, 100), [self::FAMILIES[random_int(0, 4)]]],
            Verdict::Suspicious => [random_int(30, 69), []],
            Verdict::Harmless => [random_int(0, 10), []],
            Verdict::Unknown => [0, []],
        };

        return new ProviderResult($verdict, $score, $families, $this->clock->now());
    }
}
