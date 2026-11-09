<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\UserRole;
use App\Models\Analyst;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyst_is_a_user_with_the_analyst_role(): void
    {
        $analyst = Analyst::factory()->create();

        self::assertInstanceOf(User::class, $analyst);
        self::assertSame(UserRole::Analyst, $analyst->role);
        self::assertSame(UserRole::Analyst, User::findOrFail($analyst->id)->role);
    }

    public function test_analyst_role_is_forced_even_if_another_is_given(): void
    {
        $analyst = Analyst::factory()->create(['role' => UserRole::Playbook]);

        self::assertSame(UserRole::Analyst, $analyst->fresh()?->role);
    }

    public function test_analyst_query_excludes_other_roles(): void
    {
        $analyst = Analyst::factory()->create();
        $playbook = User::factory()->playbook()->create();

        self::assertTrue(Analyst::query()->whereKey($analyst->id)->exists());
        self::assertFalse(Analyst::query()->whereKey($playbook->id)->exists());
        self::assertSame(2, User::query()->count());
    }

    public function test_users_belong_to_a_team(): void
    {
        $team = Team::factory()->create();
        $analyst = Analyst::factory()->for($team)->create();

        self::assertTrue($analyst->team->is($team));
        self::assertTrue($team->users()->whereKey($analyst->id)->exists());
    }

    public function test_ids_are_uuid_v7(): void
    {
        $analyst = Analyst::factory()->create();

        self::assertSame('7', $analyst->id[14]);
    }
}
