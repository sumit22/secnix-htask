<?php

declare(strict_types=1);

namespace App\Modules\Providers\Infrastructure\Persistence;

use App\Modules\Providers\Domain\Models\Provider;
use App\Modules\Providers\Domain\Repositories\ProviderRepository;

final class EloquentProviderRepository implements ProviderRepository
{
    public function findBySlug(string $slug): ?Provider
    {
        return Provider::query()->where('slug', $slug)->first();
    }

    public function findById(string $id): ?Provider
    {
        return Provider::query()->find($id);
    }
}
