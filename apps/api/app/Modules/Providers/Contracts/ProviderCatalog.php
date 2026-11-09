<?php

declare(strict_types=1);

namespace App\Modules\Providers\Contracts;

interface ProviderCatalog
{
    public function findBySlug(string $slug): ?ProviderRef;

    public function findById(string $id): ?ProviderRef;

    public function client(ProviderRef $provider): ThreatIntelProvider;
}
