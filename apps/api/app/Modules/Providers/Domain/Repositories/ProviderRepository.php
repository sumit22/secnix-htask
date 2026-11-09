<?php

declare(strict_types=1);

namespace App\Modules\Providers\Domain\Repositories;

use App\Modules\Providers\Domain\Models\Provider;

interface ProviderRepository
{
    public function findBySlug(string $slug): ?Provider;

    public function findById(string $id): ?Provider;
}
