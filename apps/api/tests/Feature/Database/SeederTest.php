<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\UserRole;
use App\Models\Analyst;
use App\Models\Provider;
use App\Models\QuotaAllocation;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_default_team_provider_users_and_todays_quota(): void
    {
        config(['enrichment.default_daily_quota' => 7]);
        CarbonImmutable::setTestNow('2026-11-09 12:00:00');

        $this->seed();

        $team = Team::query()->where('slug', 'default')->firstOrFail();
        $provider = Provider::query()->where('slug', config('enrichment.default_provider'))->firstOrFail();

        self::assertGreaterThanOrEqual(2, Analyst::query()->where('team_id', $team->id)->count());
        self::assertTrue(User::query()->where('role', UserRole::Playbook)->exists());

        $quota = QuotaAllocation::query()->where('provider_id', $provider->id)->whereNull('team_id')->sole();
        self::assertSame(7, $quota->quota_limit);
        self::assertSame(7, $quota->remaining());
    }

    public function test_seeding_twice_does_not_duplicate(): void
    {
        $this->seed();
        $this->seed();

        self::assertSame(1, Team::query()->count());
        self::assertSame(1, Provider::query()->count());
        self::assertSame(1, QuotaAllocation::query()->count());
    }
}
