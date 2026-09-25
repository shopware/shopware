<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\App\AppEvents;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Seo\App\AppEntitySeoUrlConfig;
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
    private TagAwareAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new TagAwareAdapter(new ArrayAdapter());
    }

    public function testWrittenAndDeletedAppsInvalidateTheRoutes(): void
    {
        static::assertSame(
            [
                AppEvents::APP_WRITTEN_EVENT => 'invalidate',
                AppEvents::APP_DELETED_EVENT => 'invalidate',
            ],
            AppSeoUrlRouteProvider::getSubscribedEvents()
        );
    }

    public function testACacheMissReturnsTheEntitySeoUrlsOfActiveAppsAsLoaded(): void
    {
        $teaser = $this->seoUrl('product-teaser', 'product');
        $blog = $this->seoUrl('blog-detail', 'ce_blog');

        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->once())
            ->method('forActiveApps')
            ->with(AppEntitySeoUrlConfig::class)
            ->willReturn([$this->feature($teaser), $this->feature($blog)]);

        static::assertSame([$teaser, $blog], (new AppSeoUrlRouteProvider($storage, $this->cache))->getEntityRoutes());
    }

    public function testTheLoadedRoutesAreCachedCompressedAndTaggedForInvalidation(): void
    {
        $teaser = $this->seoUrl('product-teaser', 'product');

        (new AppSeoUrlRouteProvider($this->storage($teaser), $this->cache))->getEntityRoutes();

        $item = $this->cache->getItem(AppSeoUrlRouteProvider::CACHE_KEY);
        static::assertTrue($item->isHit());
        static::assertEquals([$teaser], CacheValueCompressor::uncompress($item->get()));

        $this->cache->invalidateTags([AppSeoUrlRouteProvider::CACHE_KEY]);

        static::assertFalse($this->cache->getItem(AppSeoUrlRouteProvider::CACHE_KEY)->isHit());
    }

    public function testACacheHitIsServedWithoutLoadingFromTheStorage(): void
    {
        $teaser = $this->seoUrl('product-teaser', 'product');
        (new AppSeoUrlRouteProvider($this->storage($teaser), $this->cache))->getEntityRoutes();

        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->never())->method('forActiveApps');

        static::assertEquals([$teaser], (new AppSeoUrlRouteProvider($storage, $this->cache))->getEntityRoutes());
    }

    public function testTheRoutesAreMemoisedWithinTheProcess(): void
    {
        $teaser = $this->seoUrl('product-teaser', 'product');

        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->once())->method('forActiveApps')->willReturn([$this->feature($teaser)]);

        $provider = new AppSeoUrlRouteProvider($storage, $this->cache);
        $provider->getEntityRoutes();

        $this->cache->delete(AppSeoUrlRouteProvider::CACHE_KEY);

        static::assertSame([$teaser], $provider->getEntityRoutes());
    }

    public function testResetDropsTheMemoisationButKeepsTheCachedRoutes(): void
    {
        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->once())
            ->method('forActiveApps')
            ->willReturn([$this->feature($this->seoUrl('product-teaser', 'product'))]);

        $provider = new AppSeoUrlRouteProvider($storage, $this->cache);
        $provider->getEntityRoutes();

        $refreshedByAnotherProcess = $this->seoUrl('blog-detail', 'ce_blog');
        $item = $this->cache->getItem(AppSeoUrlRouteProvider::CACHE_KEY);
        $item->set(CacheValueCompressor::compress([$refreshedByAnotherProcess]));
        $this->cache->save($item);

        $provider->reset();

        static::assertEquals([$refreshedByAnotherProcess], $provider->getEntityRoutes());
    }

    public function testInvalidateDropsTheMemoisationAndTheCachedRoutes(): void
    {
        $teaser = $this->seoUrl('product-teaser', 'product');
        $blog = $this->seoUrl('blog-detail', 'ce_blog');

        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->exactly(2))
            ->method('forActiveApps')
            ->willReturnOnConsecutiveCalls([$this->feature($teaser)], [$this->feature($blog)]);

        $provider = new AppSeoUrlRouteProvider($storage, $this->cache);
        static::assertSame([$teaser], $provider->getEntityRoutes());

        $provider->invalidate();

        static::assertFalse($this->cache->getItem(AppSeoUrlRouteProvider::CACHE_KEY)->isHit());
        static::assertSame([$blog], $provider->getEntityRoutes());
    }

    private function storage(AppEntitySeoUrlConfig ...$seoUrls): AppFeatureStorage
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn(array_map($this->feature(...), $seoUrls));

        return $storage;
    }

    /**
     * @return AppFeature<AppEntitySeoUrlConfig>
     */
    private function feature(AppEntitySeoUrlConfig $seoUrl): AppFeature
    {
        return new AppFeature(
            appId: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            appName: 'SwagSeoUrlApp',
            appActive: true,
            appVersion: '1.0.0',
            appHasSecret: false,
            createdAt: new \DateTimeImmutable('2026-01-01 00:00:00'),
            config: $seoUrl,
        );
    }

    private function seoUrl(string $name, string $entityName): AppEntitySeoUrlConfig
    {
        return new AppEntitySeoUrlConfig(
            name: $name,
            routeName: 'storefront.app.SwagSeoUrlApp.' . $name,
            hook: $name,
            entityName: $entityName,
            defaultTemplate: '{{ ' . $entityName . '.translated.name }}',
        );
    }
}
