<?php

declare(strict_types=1);

namespace App\Modules\Lookups\Database\Factories;

use App\Modules\Identity\Domain\Models\Analyst;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Lookups\Contracts\Enums\LookupStatus;
use App\Modules\Lookups\Domain\Models\LookupRequest;
use App\Modules\Providers\Contracts\Enums\Verdict;
use App\Modules\Providers\Domain\Models\Provider;
use App\Modules\Shared\Indicators\IndicatorType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LookupRequest> */
final class LookupRequestFactory extends Factory
{
    protected $model = LookupRequest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $indicator = fake()->unique()->domainName();

        return [
            'provider_id' => Provider::query()->value('id') ?? Provider::factory(),
            'requested_by' => Analyst::factory(),
            'team_id' => static function (array $attributes): string {
                $requesterId = $attributes['requested_by'];
                assert(is_string($requesterId));

                return User::query()->findOrFail($requesterId)->team_id;
            },
            'indicator_type' => IndicatorType::Domain,
            'indicator_value' => $indicator,
            'status' => LookupStatus::Queued,
        ];
    }

    public function queued(): self
    {
        return $this->state(['status' => LookupStatus::Queued]);
    }

    public function leased(?CarbonImmutable $expiresAt = null): self
    {
        $now = CarbonImmutable::now();

        return $this->state([
            'status' => LookupStatus::Leased,
            'leased_at' => $now,
            'lease_expires_at' => $expiresAt ?? $now->addSeconds(300),
        ]);
    }

    public function completed(Verdict $verdict = Verdict::Harmless): self
    {
        $now = CarbonImmutable::now();

        return $this->state([
            'status' => LookupStatus::Completed,
            'leased_at' => $now->subMinute(),
            'lease_expires_at' => $now->addMinutes(4),
            'completed_at' => $now,
            'result' => ['verdict' => $verdict->value, 'score' => 0, 'malware_families' => []],
        ]);
    }

    public function expired(): self
    {
        $now = CarbonImmutable::now();

        return $this->state([
            'status' => LookupStatus::Expired,
            'leased_at' => $now->subMinutes(6),
            'lease_expires_at' => $now->subMinute(),
            'expired_at' => $now,
        ]);
    }
}
