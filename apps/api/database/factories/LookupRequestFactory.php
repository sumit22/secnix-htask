<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IndicatorType;
use App\Enums\LookupStatus;
use App\Enums\Verdict;
use App\Models\Analyst;
use App\Models\LookupRequest;
use App\Models\Provider;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'idempotency_key' => Str::uuid()->toString(),
            'request_hash' => hash('sha256', $indicator),
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
