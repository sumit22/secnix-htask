<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @template TModel of User
 *
 * @extends Factory<TModel>
 */
class UserFactory extends Factory
{
    /** @var class-string<TModel> */
    protected $model = User::class;

    protected static ?string $password;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make(Str::random(32)),
            'remember_token' => Str::random(10),
            'role' => UserRole::Analyst,
            'team_id' => Team::factory(),
        ];
    }

    public function playbook(): static
    {
        return $this->state(['role' => UserRole::Playbook]);
    }
}
