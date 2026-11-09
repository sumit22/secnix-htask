<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Identity\Contracts\Enums\UserRole;
use App\Modules\Identity\Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Anyone who can request lookups; subclassed by role (single-table inheritance), e.g. Analyst.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property string $team_id
 * @property-read Team $team
 */
#[Fillable(['name', 'email', 'password', 'role', 'team_id'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected $table = 'users';

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
