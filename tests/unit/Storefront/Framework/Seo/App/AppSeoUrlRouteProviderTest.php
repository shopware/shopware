<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute\AppSeoUrlRouteEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlRouteProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlRouteProvider::class)]
class AppSeoUrlRouteProviderTest extends TestCase
{
    private const APP_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const OTHER_APP_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private TagAwareAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new TagAwareAdapter(new ArrayAdapter());
    }

    public function testWrittenAppsAndRoutesInvalidateTheCache(): void
    {
        static::assertSame(
            [
                'app.written' => 'invalidate',
                'app.deleted' => 'invalidate',
                'app_seo_url_route.written' => 'invalidate',
                'app_seo_url_route.deleted' => 'invalidate',
            ],
            AppSeoUrlRouteProvider::getSubscribedEvents()
        );
    }

    public function testEntityBoundAndStaticRoutesAreSeparated(): void
    {
        $provider = $this->provider(
            $this->staticRoute('imprint', ['en-GB' => 'imprint']),
            $this->entityRoute('product-teaser', 'product')
        );

        static::assertSame(
            ['storefront.app.SwagSeoUrlApp.product-teaser'],
            $this->routeNames($provider->getEntityRoutes())
        );
        static::assertSame(
            ['storefront.app.SwagSeoUrlApp.imprint'],
            $this->routeNames($provider->getStaticRoutes())
        );
    }

    public function testStaticRoutesWithoutAnyPathAreIgnored(): void
    {
        $provider = $this->provider(
            $this->staticRoute('imprint', null),
            $this->staticRoute('contact', [])
        );

        static::assertSame([], $this->routeNames($provider->getStaticRoutes()));
    }

    public function testEntityRoutesWithoutADefaultTemplateAreIgnored(): void
    {
        $route = $this->entityRoute('product-teaser', 'product');
        $route->defaultTemplate = null;

        $provider = $this->provider($route);

        static::assertSame([], $this->routeNames($provider->getEntityRoutes()));
        static::assertSame([], $this->routeNames($provider->getStaticRoutes()));
    }

    public function testRoutesCanBeFilteredByApp(): void
    {
        $otherStatic = $this->staticRoute('other-imprint', ['en-GB' => 'other-imprint']);
        $otherStatic->appId = self::OTHER_APP_ID;
        $otherEntity = $this->entityRoute('other-teaser', 'product');
        $otherEntity->appId = self::OTHER_APP_ID;

        $provider = $this->provider(
            $this->staticRoute('imprint', ['en-GB' => 'imprint']),
            $this->entityRoute('product-teaser', 'product'),
            $otherStatic,
            $otherEntity
        );

        static::assertSame(
            ['storefront.app.SwagSeoUrlApp.imprint'],
            $this->routeNames($provider->getStaticRoutes(self::APP_ID))
        );
        static::assertSame(
            ['storefront.app.SwagSeoUrlApp.product-teaser'],
            $this->routeNames($provider->getEntityRoutes(self::APP_ID))
        );
        static::assertSame(
            ['storefront.app.SwagSeoUrlApp.other-imprint'],
            $this->routeNames($provider->getStaticRoutes(self::OTHER_APP_ID))
        );
        static::assertSame(
            ['storefront.app.SwagSeoUrlApp.other-teaser'],
            $this->routeNames($provider->getEntityRoutes(self::OTHER_APP_ID))
        );
    }

    public function testOnlyTheRoutesOfActiveAppsAreLoaded(): void
    {
        $criteria = null;
        $route = $this->staticRoute('imprint', ['en-GB' => 'imprint']);

        $repository = new StaticEntityRepository([
            static function (Criteria $given) use (&$criteria, $route): EntityCollection {
                $criteria = $given;

                return new EntityCollection([$route]);
            },
        ]);

        (new AppSeoUrlRouteProvider($repository, $this->cache))->getStaticRoutes();

        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertEquals([new EqualsFilter('app.active', true)], $criteria->getFilters());
    }

    public function testTheCacheMissStoresTheRoutesCompressed(): void
    {
        $this->provider($this->staticRoute('imprint', ['en-GB' => 'imprint']))->getStaticRoutes();

        $item = $this->cache->getItem(AppSeoUrlRouteProvider::CACHE_KEY);
        static::assertTrue($item->isHit());

        $cached = CacheValueCompressor::uncompress($item->get());
        static::assertInstanceOf(EntityCollection::class, $cached);
        static::assertSame(['storefront.app.SwagSeoUrlApp.imprint'], $this->routeNames($cached));
    }

    public function testTheCachedRoutesAreTaggedForInvalidation(): void
    {
        $this->provider($this->staticRoute('imprint', ['en-GB' => 'imprint']))->getStaticRoutes();

        $this->cache->invalidateTags([AppSeoUrlRouteProvider::CACHE_KEY]);

        static::assertFalse($this->cache->getItem(AppSeoUrlRouteProvider::CACHE_KEY)->isHit());
    }

    public function testASecondProviderReadsTheRoutesFromTheSharedCache(): void
    {
        $this->provider($this->staticRoute('imprint', ['en-GB' => 'imprint']))->getStaticRoutes();

        $repository = $this->repository();
        $cold = new AppSeoUrlRouteProvider($repository, $this->cache);

        static::assertSame(
            ['storefront.app.SwagSeoUrlApp.imprint'],
            $this->routeNames($cold->getStaticRoutes())
        );
        static::assertCount(2, $repository->searches);
    }

    public function testTheRepositoryIsQueriedOnceWhileTheRoutesAreMemoised(): void
    {
        $repository = $this->repository($this->staticRoute('imprint', ['en-GB' => 'imprint']));
        $provider = new AppSeoUrlRouteProvider($repository, $this->cache);

        $provider->getStaticRoutes();
        $provider->getEntityRoutes();
        $provider->getStaticRoutes();

        static::assertCount(1, $repository->searches);
    }

    public function testResetOnlyDropsTheInProcessMemoisation(): void
    {
        $repository = $this->repository($this->staticRoute('imprint', ['en-GB' => 'imprint']));
        $provider = new AppSeoUrlRouteProvider($repository, $this->cache);

        $provider->getStaticRoutes();
        $provider->reset();

        static::assertCount(1, $provider->getStaticRoutes());
        static::assertCount(1, $repository->searches);
        static::assertTrue($this->cache->getItem(AppSeoUrlRouteProvider::CACHE_KEY)->isHit());
    }

    public function testInvalidateAlsoDeletesTheCachedRoutes(): void
    {
        $repository = $this->repository($this->staticRoute('imprint', ['en-GB' => 'imprint']));
        $provider = new AppSeoUrlRouteProvider($repository, $this->cache);

        $provider->getStaticRoutes();
        $provider->invalidate();

        static::assertFalse($this->cache->getItem(AppSeoUrlRouteProvider::CACHE_KEY)->isHit());

        static::assertCount(1, $provider->getStaticRoutes());
        static::assertSame([], $repository->searches);
    }

    private function provider(AppSeoUrlRouteEntity ...$routes): AppSeoUrlRouteProvider
    {
        return new AppSeoUrlRouteProvider($this->repository(...$routes), $this->cache);
    }

    /**
     * @return StaticEntityRepository<EntityCollection<AppSeoUrlRouteEntity>>
     */
    private function repository(AppSeoUrlRouteEntity ...$routes): StaticEntityRepository
    {
        return new StaticEntityRepository([new EntityCollection($routes), new EntityCollection($routes)]);
    }

    /**
     * @param EntityCollection<AppSeoUrlRouteEntity> $routes
     *
     * @return list<string>
     */
    private function routeNames(EntityCollection $routes): array
    {
        return array_values($routes->map(static fn (AppSeoUrlRouteEntity $route): string => $route->routeName));
    }

    /**
     * @param array<string, string>|null $paths
     */
    private function staticRoute(string $name, ?array $paths): AppSeoUrlRouteEntity
    {
        $route = $this->route($name);
        $route->paths = $paths;

        return $route;
    }

    private function entityRoute(string $name, string $entityName): AppSeoUrlRouteEntity
    {
        $route = $this->route($name);
        $route->entityName = $entityName;
        $route->defaultTemplate = '{{ ' . $entityName . '.translated.name }}';

        return $route;
    }

    private function route(string $name): AppSeoUrlRouteEntity
    {
        $route = new AppSeoUrlRouteEntity();
        $route->id = Uuid::randomHex();
        $route->appId = self::APP_ID;
        $route->name = $name;
        $route->routeName = 'storefront.app.SwagSeoUrlApp.' . $name;
        $route->hook = $name;
        $route->setUniqueIdentifier($route->id);

        return $route;
    }
}
