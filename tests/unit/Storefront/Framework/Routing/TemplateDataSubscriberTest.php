<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\Hreflang\HreflangCollection;
use Shopware\Core\Content\Seo\HreflangLoaderInterface;
use Shopware\Core\Content\Seo\HreflangLoaderParameter;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\TemplateDataSubscriber;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopware\Storefront\Page\Product\ProductPage;
use Shopware\Storefront\Theme\ThemeRuntimeConfig;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TemplateDataSubscriber::class)]
class TemplateDataSubscriberTest extends TestCase
{
    private HreflangLoaderInterface&MockObject $hreflangLoader;

    private ShopIdProvider&Stub $shopIdProvider;

    private ActiveAppsLoader&Stub $activeAppsLoader;

    private TemplateDataSubscriber $subscriber;

    private ThemeRuntimeConfigService&Stub $themeRuntimeConfigService;

    protected function setUp(): void
    {
        $this->hreflangLoader = static::createMock(HreflangLoaderInterface::class);
        $this->shopIdProvider = static::createStub(ShopIdProvider::class);
        $this->activeAppsLoader = static::createStub(ActiveAppsLoader::class);
        $this->themeRuntimeConfigService = static::createStub(ThemeRuntimeConfigService::class);

        $this->subscriber = $this->buildSubscriber();
    }

    public function testGetSubscribedEvents(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $events = TemplateDataSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(StorefrontRenderEvent::class, $events);

        static::assertArrayHasKey(StorefrontRenderEvent::class, $events);
        static::assertIsArray($events[StorefrontRenderEvent::class]);
        static::assertCount(3, $events[StorefrontRenderEvent::class]);

        static::assertArrayHasKey('0', $events[StorefrontRenderEvent::class]);
        static::assertIsArray($events[StorefrontRenderEvent::class][0]);
        static::assertArrayHasKey('0', $events[StorefrontRenderEvent::class][0]);
        static::assertSame('addHreflang', $events[StorefrontRenderEvent::class][0][0]);

        static::assertArrayHasKey('1', $events[StorefrontRenderEvent::class]);
        static::assertIsArray($events[StorefrontRenderEvent::class][1]);
        static::assertSame('addShopIdParameter', $events[StorefrontRenderEvent::class][1][0]);

        static::assertArrayHasKey('2', $events[StorefrontRenderEvent::class]);
        static::assertIsArray($events[StorefrontRenderEvent::class][2]);
        static::assertSame('addIconSetConfig', $events[StorefrontRenderEvent::class][2][0]);
    }

    public function testAddHreflangWithNullRoute(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $event = new StorefrontRenderEvent(
            'test',
            [],
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $hreflangLoader = $this->createMock(HreflangLoaderInterface::class);
        $hreflangLoader->expects($this->never())->method('load');

        $subscriber = $this->buildSubscriber(hreflangLoader: $hreflangLoader);

        $subscriber->addHreflang($event);
    }

    public function testAddHreflangSkippedForEsiRequest(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $request = new Request();
        $request->attributes->set('_route', 'frontend.header');
        $request->attributes->set('_esi', true);

        $event = new StorefrontRenderEvent(
            'test',
            [],
            $request,
            Generator::generateSalesChannelContext()
        );

        $hreflangLoader = $this->createMock(HreflangLoaderInterface::class);
        $hreflangLoader->expects($this->never())->method('load');

        $subscriber = $this->buildSubscriber(hreflangLoader: $hreflangLoader);

        $subscriber->addHreflang($event);
    }

    public function testAddHreflangWithValidRoute(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $request = new Request();
        $request->attributes->set('_route', 'frontend.home');
        $request->attributes->set('_route_params', ['param' => 'value']);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, Generator::generateSalesChannelContext());

        $event = new StorefrontRenderEvent(
            'test',
            [],
            $request,
            Generator::generateSalesChannelContext()
        );

        $hreflangLoader = $this->createMock(HreflangLoaderInterface::class);
        $hreflangLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn(new HreflangCollection());

        $subscriber = $this->buildSubscriber(hreflangLoader: $hreflangLoader);

        $subscriber->addHreflang($event);

        static::assertInstanceOf(HreflangCollection::class, $event->getParameters()['hrefLang']);
    }

    public function testAddHreflangUsesCanonicalProductIdWhenSet(): void
    {
        $variantProductId = 'variant-product-id';
        $canonicalProductId = 'canonical-product-id';

        $request = new Request();
        $request->attributes->set('_route', ProductPageSeoUrlRoute::ROUTE_NAME);
        $request->attributes->set('_route_params', ['productId' => $variantProductId]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, Generator::generateSalesChannelContext());

        $product = new SalesChannelProductEntity();
        $product->setCanonicalProductId($canonicalProductId);

        $page = new ProductPage();
        $page->setProduct($product);

        $event = new StorefrontRenderEvent(
            'test',
            ['page' => $page],
            $request,
            Generator::generateSalesChannelContext()
        );

        $this->hreflangLoader
            ->expects($this->once())
            ->method('load')
            ->with(static::callback(static function (HreflangLoaderParameter $parameter) use ($canonicalProductId): bool {
                return $parameter->getRouteParameters()['productId'] === $canonicalProductId;
            }))
            ->willReturn(new HreflangCollection());

        $this->subscriber->addHreflang($event);
    }

    public function testAddHreflangUsesProductIdWhenNoCanonicalProductId(): void
    {
        $productId = 'parent-product-id';

        $request = new Request();
        $request->attributes->set('_route', ProductPageSeoUrlRoute::ROUTE_NAME);
        $request->attributes->set('_route_params', ['productId' => $productId]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, Generator::generateSalesChannelContext());

        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        $page = new ProductPage();
        $page->setProduct($product);

        $event = new StorefrontRenderEvent(
            'test',
            ['page' => $page],
            $request,
            Generator::generateSalesChannelContext()
        );

        $this->hreflangLoader
            ->expects($this->once())
            ->method('load')
            ->with(static::callback(static function (HreflangLoaderParameter $parameter) use ($productId): bool {
                return $parameter->getRouteParameters()['productId'] === $productId;
            }))
            ->willReturn(new HreflangCollection());

        $this->subscriber->addHreflang($event);
    }

    public function testAddHreflangUsesVariantProductIdWhenParentRouteIsUsed(): void
    {
        $parentProductId = 'parent-product-id';
        $variantProductId = 'parent-product-id';

        $request = new Request();
        $request->attributes->set('_route', ProductPageSeoUrlRoute::ROUTE_NAME);
        $request->attributes->set('_route_params', ['productId' => $parentProductId]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, Generator::generateSalesChannelContext());

        $product = new SalesChannelProductEntity();
        $product->setId($variantProductId);

        $page = new ProductPage();
        $page->setProduct($product);

        $event = new StorefrontRenderEvent(
            'test',
            ['page' => $page],
            $request,
            Generator::generateSalesChannelContext()
        );

        $this->hreflangLoader
            ->expects($this->once())
            ->method('load')
            ->with(static::callback(static function (HreflangLoaderParameter $parameter) use ($variantProductId): bool {
                return $parameter->getRouteParameters()['productId'] === $variantProductId;
            }))
            ->willReturn(new HreflangCollection());

        $this->subscriber->addHreflang($event);
    }

    public function testAddShopIdParameterWithNoActiveApps(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $event = new StorefrontRenderEvent(
            'test',
            [],
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->activeAppsLoader
            ->method('getActiveApps')
            ->willReturn([]);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider
            ->expects($this->never())
            ->method('getShopId');

        $subscriber = $this->buildSubscriber(shopIdProvider: $shopIdProvider);

        $subscriber->addShopIdParameter($event);
    }

    public function testAddShopIdParameterWithUrlChangeException(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $event = new StorefrontRenderEvent(
            'test',
            [],
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->activeAppsLoader
            ->method('getActiveApps')
            ->willReturn(['someApp']);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider
            ->expects($this->once())
            ->method('getShopId')
            ->willThrowException(new ShopIdChangeSuggestedException(ShopId::v2('123'), new FingerprintComparisonResult([], [], 75)));

        $subscriber = $this->buildSubscriber(shopIdProvider: $shopIdProvider);

        $subscriber->addShopIdParameter($event);
    }

    public function testShopIdAdded(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $event = new StorefrontRenderEvent(
            'test',
            [],
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->activeAppsLoader
            ->method('getActiveApps')
            ->willReturn(['someApp']);

        $shopId = ShopId::v2('123');
        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider
            ->expects($this->once())
            ->method('getShopId')
            ->willReturn($shopId);

        $subscriber = $this->buildSubscriber(shopIdProvider: $shopIdProvider);

        $subscriber->addShopIdParameter($event);

        static::assertSame('123', $event->getParameters()['appShopId']);
    }

    public function testAddIconSetConfigWithNoTheme(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $event = new StorefrontRenderEvent(
            'test',
            [],
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService
            ->expects($this->never())
            ->method('getRuntimeConfigByName');

        $subscriber = $this->buildSubscriber(themeRuntimeConfigService: $themeRuntimeConfigService);

        $subscriber->addIconSetConfig($event);
    }

    public function testAddIconSetConfigWithNoThemeButThemeName(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'Storefront');

        $event = new StorefrontRenderEvent(
            'test',
            [],
            $request,
            Generator::generateSalesChannelContext()
        );

        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService
            ->expects($this->once())
            ->method('getRuntimeConfigByName');

        $subscriber = $this->buildSubscriber(themeRuntimeConfigService: $themeRuntimeConfigService);

        $subscriber->addIconSetConfig($event);
        static::assertArrayNotHasKey('themeIconConfig', $event->getParameters());
    }

    public function testAddIconSetConfigWithValidTheme(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'Storefront');

        $event = new StorefrontRenderEvent(
            'test',
            [],
            $request,
            Generator::generateSalesChannelContext()
        );

        $themeConfig = ThemeRuntimeConfig::fromArray([
            'themeId' => '123',
            'technicalName' => 'Storefront',
            'resolvedConfig' => [],
            'iconSets' => ['default' => ['path' => '@Storefront/icons/default', 'namespace' => '']],
            'updatedAt' => new \DateTime(),
        ]);

        $this->themeRuntimeConfigService
            ->method('getRuntimeConfigByName')
            ->willReturn($themeConfig);

        $this->subscriber->addIconSetConfig($event);

        static::assertArrayHasKey('themeIconConfig', $event->getParameters());
        static::assertSame($themeConfig->iconSets, $event->getParameters()['themeIconConfig']);
    }

    private function buildSubscriber(
        ?HreflangLoaderInterface $hreflangLoader = null,
        ?ShopIdProvider $shopIdProvider = null,
        ?ActiveAppsLoader $activeAppsLoader = null,
        ?ThemeRuntimeConfigService $themeRuntimeConfigService = null,
    ): TemplateDataSubscriber {
        return new TemplateDataSubscriber(
            $hreflangLoader ?? $this->hreflangLoader,
            $shopIdProvider ?? $this->shopIdProvider,
            $activeAppsLoader ?? $this->activeAppsLoader,
            $themeRuntimeConfigService ?? $this->themeRuntimeConfigService,
        );
    }
}
