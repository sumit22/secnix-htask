<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Providers;

use App\Modules\Providers\Contracts\ProviderCatalog;
use App\Modules\Providers\Domain\Models\Provider;
use App\Modules\Providers\Infrastructure\Clients\MockThreatIntelProvider;
use App\Modules\Shared\Indicators\Indicator;
use App\Modules\Shared\Indicators\IndicatorType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProviderCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_provider_by_slug_and_id(): void
    {
        $provider = Provider::factory()->create(['slug' => MockThreatIntelProvider::SLUG]);
        $catalog = $this->app->make(ProviderCatalog::class);

        self::assertSame($provider->id, $catalog->findBySlug(MockThreatIntelProvider::SLUG)?->id);
        self::assertSame(MockThreatIntelProvider::SLUG, $catalog->findById($provider->id)?->slug);
        self::assertNull($catalog->findBySlug('unknown'));
    }

    public function test_mock_client_returns_a_bounded_reputation(): void
    {
        Provider::factory()->create(['slug' => MockThreatIntelProvider::SLUG]);
        $catalog = $this->app->make(ProviderCatalog::class);
        $ref = $catalog->findBySlug(MockThreatIntelProvider::SLUG);
        self::assertNotNull($ref);

        $result = $catalog->client($ref)->lookup(new Indicator(IndicatorType::Ip, '8.8.8.8'));

        self::assertGreaterThanOrEqual(0, $result->score);
        self::assertLessThanOrEqual(100, $result->score);
        self::assertArrayHasKey('verdict', $result->toArray());
    }
}
