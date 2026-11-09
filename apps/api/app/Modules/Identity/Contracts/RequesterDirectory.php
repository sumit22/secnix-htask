<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

interface RequesterDirectory
{
    public function find(string $id): ?Requester;

    /** @return list<Requester> */
    public function analysts(): array;
}
