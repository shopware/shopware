<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEvents;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Seo\App\AppEntitySeoUrlConfig;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlRouteProvider;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlRouteProvider::class)]
class AppSeoUrlRouteProviderTest extends TestCase
{
    public function testWrittenAndDeletedAppsResetTheRoutes(): void
    {
        static::assertSame(
            [
                AppEvents::APP_WRITTEN_EVENT => 'reset',
                AppEvents::APP_DELETED_EVENT => 'reset',
            ],
            AppSeoUrlRouteProvider::getSubscribedEvents()
        );
    }

    public function testTheEntitySeoUrlsOfActiveAppsAreLoadedOnce(): void
    {
        $teaser = $this->seoUrl('product-teaser', 'product');
        $blog = $this->seoUrl('blog-detail', 'ce_blog');

        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->once())
            ->method('forActiveApps')
            ->with(AppEntitySeoUrlConfig::class)
            ->willReturn([$this->feature($teaser), $this->feature($blog)]);

        $provider = new AppSeoUrlRouteProvider($storage);

        static::assertSame([$teaser, $blog], $provider->getEntityRoutes());
        static::assertSame([$teaser, $blog], $provider->getEntityRoutes());
    }

    public function testResetLoadsTheRoutesAgain(): void
    {
        $teaser = $this->seoUrl('product-teaser', 'product');
        $blog = $this->seoUrl('blog-detail', 'ce_blog');

        $storage = $this->createMock(AppFeatureStorage::class);
        $storage->expects($this->exactly(2))
            ->method('forActiveApps')
            ->willReturnOnConsecutiveCalls([$this->feature($teaser)], [$this->feature($blog)]);

        $provider = new AppSeoUrlRouteProvider($storage);
        static::assertSame([$teaser], $provider->getEntityRoutes());

        $provider->reset();

        static::assertSame([$blog], $provider->getEntityRoutes());
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
