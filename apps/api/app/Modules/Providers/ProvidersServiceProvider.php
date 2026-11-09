<?php

declare(strict_types=1);

namespace App\Modules\Providers;

use App\Modules\Providers\Application\ProviderCatalogService;
use App\Modules\Providers\Contracts\ProviderCatalog;
use App\Modules\Providers\Domain\Repositories\ProviderRepository;
use App\Modules\Providers\Infrastructure\Clients\MockThreatIntelProvider;
use App\Modules\Providers\Infrastructure\Persistence\EloquentProviderRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ProvidersServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        ProviderRepository::class => EloquentProviderRepository::class,
    ];

    public function register(): void
    {
        // Real providers get registered here by slug.
        $this->app->singleton(ProviderCatalog::class, static fn (Application $app): ProviderCatalog => new ProviderCatalogService(
            $app->make(ProviderRepository::class),
            [MockThreatIntelProvider::SLUG => $app->make(MockThreatIntelProvider::class)],
        ));
    }
}
