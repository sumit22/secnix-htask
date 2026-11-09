<?php

declare(strict_types=1);

namespace App\Modules\Quota\Database\Factories;

use App\Modules\Providers\Domain\Models\Provider;
use App\Modules\Quota\Domain\Models\QuotaAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuotaAllocation> */
final class QuotaAllocationFactory extends Factory
{
    protected $model = QuotaAllocation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $start = CarbonImmutable::now('UTC')->startOfDay();

        return [
            'provider_id' => Provider::factory(),
            'team_id' => null,
            'period_start' => $start,
            'period_end' => $start->addDay(),
            'quota_limit' => 10,
            'reserved' => 0,
            'consumed' => 0,
        ];
    }
}
