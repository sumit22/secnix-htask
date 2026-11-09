<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Contracts\Requester;
use App\Modules\Identity\Contracts\RequesterDirectory;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Repositories\UserRepository;

final readonly class RequesterDirectoryService implements RequesterDirectory
{
    public function __construct(private UserRepository $users) {}

    public function find(string $id): ?Requester
    {
        $user = $this->users->findWithTeam($id);

        return $user === null ? null : self::toRequester($user);
    }

    public function analysts(): array
    {
        return array_map(self::toRequester(...), $this->users->analystsWithTeam());
    }

    private static function toRequester(User $user): Requester
    {
        return new Requester($user->id, $user->name, $user->role, $user->team_id, $user->team->slug);
    }
}
