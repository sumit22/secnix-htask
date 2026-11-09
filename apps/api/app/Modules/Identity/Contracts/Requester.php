<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use App\Modules\Identity\Contracts\Enums\UserRole;

/** The caller of a request, as seen by other modules. */
final readonly class Requester
{
    public function __construct(
        public string $id,
        public string $name,
        public UserRole $role,
        public string $teamId,
        public string $teamSlug,
    ) {}
}
