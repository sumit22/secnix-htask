<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Contracts\Enums\UserRole;
use App\Modules\Identity\Contracts\RequesterDirectory;
use App\Modules\Identity\Domain\Models\Analyst;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RequesterDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_returns_requester_with_team(): void
    {
        $analyst = Analyst::factory()->create();

        $requester = $this->app->make(RequesterDirectory::class)->find($analyst->id);

        self::assertNotNull($requester);
        self::assertSame($analyst->team_id, $requester->teamId);
        self::assertSame($analyst->team->slug, $requester->teamSlug);
        self::assertSame(UserRole::Analyst, $requester->role);
    }

    public function test_find_returns_null_for_unknown_or_malformed_ids(): void
    {
        $directory = $this->app->make(RequesterDirectory::class);

        self::assertNull($directory->find('019a0000-0000-7000-8000-000000000000'));
        self::assertNull($directory->find('not-a-uuid'));
    }

    public function test_analysts_excludes_other_roles(): void
    {
        Analyst::factory()->count(2)->create();
        User::factory()->playbook()->create();

        self::assertCount(2, $this->app->make(RequesterDirectory::class)->analysts());
    }

    public function test_analysts_endpoint_lists_analysts(): void
    {
        $analyst = Analyst::factory()->create(['name' => 'Alice']);

        $this->getJson('/api/v1/analysts')
            ->assertOk()
            ->assertExactJson(['data' => [[
                'id' => $analyst->id,
                'name' => 'Alice',
                'team' => ['id' => $analyst->team_id, 'slug' => $analyst->team->slug],
            ]]]);
    }
}
