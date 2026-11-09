<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Repositories;

use App\Modules\Identity\Domain\Models\Analyst;
use App\Modules\Identity\Domain\Models\User;

interface UserRepository
{
    public function findWithTeam(string $id): ?User;

    /** @return list<Analyst> */
    public function analystsWithTeam(): array;
}
