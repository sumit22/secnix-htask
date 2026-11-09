<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Analyst;
use App\Models\Provider;
use App\Models\QuotaAllocation;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Reference data: one default team, the mock provider, a few analysts, one playbook and today's
 * org-level quota. Idempotent — safe to run on every deploy.
 */
final class DatabaseSeeder extends Seeder
{
    private const array ANALYSTS = [
        'alice@analysts.local' => 'Alice (Tier 1)',
        'bob@analysts.local' => 'Bob (Tier 2)',
        'carol@analysts.local' => 'Carol (Threat Hunter)',
    ];

    public function run(): void
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

        QuotaAllocation::ensureOrgAllocation(
            $provider,
            CarbonImmutable::now(),
            config()->integer('enrichment.default_daily_quota'),
        );
    }
}
