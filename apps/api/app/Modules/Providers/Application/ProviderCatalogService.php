<?php

declare(strict_types=1);

namespace App\Modules\Providers\Application;

use App\Modules\Providers\Contracts\ProviderCatalog;
use App\Modules\Providers\Contracts\ProviderRef;
use App\Modules\Providers\Contracts\ThreatIntelProvider;
use App\Modules\Providers\Domain\Models\Provider;
use App\Modules\Providers\Domain\Repositories\ProviderRepository;
use LogicException;

final readonly class ProviderCatalogService implements ProviderCatalog
{
    /** @param array<string, ThreatIntelProvider> $clients keyed by provider slug */
    public function __construct(
        private ProviderRepository $providers,
        private array $clients,
    ) {}

    public function findBySlug(string $slug): ?ProviderRef
    {
        return self::toRef($this->providers->findBySlug($slug));
    }

    public function findById(string $id): ?ProviderRef
    {
        return self::toRef($this->providers->findById($id));
    }

    public function client(ProviderRef $provider): ThreatIntelProvider
    {
        return $this->clients[$provider->slug]
            ?? throw new LogicException("No client registered for provider [{$provider->slug}].");
    }

    private static function toRef(?Provider $provider): ?ProviderRef
    {
        return $provider === null ? null : new ProviderRef($provider->id, $provider->slug);
    }
}
