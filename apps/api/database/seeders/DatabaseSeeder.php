<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Contracts\Enums\UserRole;
use App\Modules\Identity\Domain\Models\Analyst;
use App\Modules\Identity\Domain\Models\Team;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Providers\Domain\Models\Provider;
use App\Modules\Quota\Domain\Repositories\QuotaAllocationRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Idempotent reference data: default team, mock provider, analysts, a playbook and today's org quota. */
final class DatabaseSeeder extends Seeder
{
    private const array ANALYSTS = [
        'alice@analysts.local' => 'Alice (Tier 1)',
        'bob@analysts.local' => 'Bob (Tier 2)',
        'carol@analysts.local' => 'Carol (Threat Hunter)',
    ];

    public function run(QuotaAllocationRepository $quotas): void
    {
        $team = Team::query()->firstOrCreate(['slug' => 'default'], ['name' => 'Default Team']);

        $provider = Provider::query()->firstOrCreate(
            ['slug' => config()->string('enrichment.default_provider')],
            ['name' => 'Mock Threat Intel'],
        );

        foreach (self::ANALYSTS as $email => $name) {
            Analyst::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'team_id' => $team->id, 'password' => Str::random(32)],
            );
        }

        User::query()->firstOrCreate(
            ['email' => 'soar-playbook@automation.local'],
            ['name' => 'SOAR Playbook', 'role' => UserRole::Playbook, 'team_id' => $team->id, 'password' => Str::random(32)],
        );

        $quotas->ensureOrgAllocation(
            $provider->id,
            CarbonImmutable::now(),
            config()->integer('enrichment.default_daily_quota'),
        );
    }
}
