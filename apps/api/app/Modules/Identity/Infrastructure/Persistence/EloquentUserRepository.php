<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Domain\Models\Analyst;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Repositories\UserRepository;
use Illuminate\Support\Str;

final class EloquentUserRepository implements UserRepository
{
    public function findWithTeam(string $id): ?User
    {
        return Str::isUuid($id) ? User::query()->with('team')->find($id) : null;
    }

    public function analystsWithTeam(): array
    {
        return array_values(Analyst::query()->with('team')->orderBy('name')->get()->all());
    }
}
