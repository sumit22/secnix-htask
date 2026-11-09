<?php

declare(strict_types=1);

namespace App\Modules\Lookups;

use App\Modules\Lookups\Application\ExpireLeasesService;
use App\Modules\Lookups\Application\LeaseManager;
use App\Modules\Lookups\Application\LookupQueryService;
use App\Modules\Lookups\Application\RequestEnrichmentService;
use App\Modules\Lookups\Application\RunLookupService;
use App\Modules\Lookups\Contracts\ExpireLeases;
use App\Modules\Lookups\Contracts\LookupQuery;
use App\Modules\Lookups\Contracts\RequestEnrichment;
use App\Modules\Lookups\Contracts\RunLookup;
use App\Modules\Lookups\Domain\Repositories\IdempotencyKeyRepository;
use App\Modules\Lookups\Domain\Repositories\LookupRequestRepository;
use App\Modules\Lookups\Domain\Repositories\WaitingQueue;
use App\Modules\Lookups\Infrastructure\Persistence\EloquentIdempotencyKeyRepository;
use App\Modules\Lookups\Infrastructure\Persistence\EloquentLookupRequestRepository;
use App\Modules\Lookups\Infrastructure\Persistence\EloquentWaitingQueue;
use Illuminate\Support\ServiceProvider;

final class LookupsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        LookupRequestRepository::class => EloquentLookupRequestRepository::class,
        IdempotencyKeyRepository::class => EloquentIdempotencyKeyRepository::class,
        WaitingQueue::class => EloquentWaitingQueue::class,
        RequestEnrichment::class => RequestEnrichmentService::class,
        RunLookup::class => RunLookupService::class,
        ExpireLeases::class => ExpireLeasesService::class,
        LookupQuery::class => LookupQueryService::class,
    ];

    public function register(): void
    {
        $ttl = static fn (): int => config()->integer('enrichment.lease_ttl_seconds');
        $provider = static fn (): string => config()->string('enrichment.default_provider');

        $this->app->when([LeaseManager::class, LookupQueryService::class])->needs('$leaseTtlSeconds')->give($ttl);
        $this->app->when([RequestEnrichmentService::class, LookupQueryService::class])->needs('$defaultProvider')->give($provider);
    }
}
