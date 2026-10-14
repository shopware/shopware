<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\Garan\GaranLabelDurationFormatter;
use Shopware\Core\Content\Product\Garan\GaranLabelInlineImage;
use Shopware\Core\Content\Product\Garan\GaranLabelRenderer;
use Shopware\Core\Content\Product\Garan\GaranLabelResolver;
use Shopware\Core\Content\Product\Garan\GaranLabelTwigFilter;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\Hreflang\HreflangCollection;
use Shopware\Core\Content\Seo\HreflangLoaderInterface;
use Shopware\Core\Content\Seo\HreflangLoaderParameter;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\TemplateDataSubscriber;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPage;
use Shopware\Storefront\Page\Account\Order\AccountOrderPage;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPage;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPage;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPage;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPage;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Page\Product\ProductPage;
use Shopware\Storefront\Theme\ThemeRuntimeConfig;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

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
        static::assertCount(4, $events[StorefrontRenderEvent::class]);

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

        static::assertArrayHasKey('3', $events[StorefrontRenderEvent::class]);
        static::assertIsArray($events[StorefrontRenderEvent::class][3]);
        static::assertSame('preloadGaranLabelProducts', $events[StorefrontRenderEvent::class][3][0]);
    }

    /**
     * @param list<string> $expectedIds
     */
    #[DataProvider('garanLabelPageProvider')]
    public function testPreloadGaranLabelProductsSearchesThemWithTheFirstRenderedLabel(?Page $page, array $expectedIds): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $searches = [];
        $filter = $this->createGaranLabelTwigFilter(static function (Criteria $criteria) use (&$searches): ProductCollection {
            $searches[] = $criteria->getIds();

            return new ProductCollection();
        });

        $event = new StorefrontRenderEvent('test', ['page' => $page], new Request(), Generator::generateSalesChannelContext());

        $this->buildSubscriber(garanLabelTwigFilter: $filter)->preloadGaranLabelProducts($event);
        $filter->renderNestedLabel('rendered-product', $event->getContext());

        static::assertSame([[...$expectedIds, 'rendered-product']], $searches);
    }

    public static function garanLabelPageProvider(): \Generator
    {
        $container = new LineItem('container', LineItem::CONTAINER_LINE_ITEM);
        $container->addChild(new LineItem('child', LineItem::PRODUCT_LINE_ITEM_TYPE, 'product-2'));

        $cart = new Cart('token');
        $cart->add(new LineItem('product', LineItem::PRODUCT_LINE_ITEM_TYPE, 'product-1'));
        $cart->add(new LineItem('promotion', LineItem::PROMOTION_LINE_ITEM_TYPE, 'promotion-1'));
        $cart->add(new LineItem('unreferenced', LineItem::PRODUCT_LINE_ITEM_TYPE));
        $cart->add($container);

        foreach ([new CheckoutCartPage(), new CheckoutConfirmPage(), new OffcanvasCartPage(), new CheckoutRegisterPage()] as $cartPage) {
            $cartPage->setCart($cart);

            yield $cartPage::class . ' preloads referenced products including container children' => [$cartPage, ['product-1', 'product-2']];
        }

        $finishPage = new CheckoutFinishPage();
        $finishPage->setOrder(self::createOrder(['product-1', 'product-2']));

        yield 'finish page preloads the order products' => [$finishPage, ['product-1', 'product-2']];

        $editOrder = self::createOrder(['product-1', 'product-4']);
        $editOrder->getLineItems()?->last()?->setProductId(null);
        $editOrderPage = new AccountEditOrderPage();
        $editOrderPage->setOrder($editOrder);

        yield 'edit order preloads the referenced id of a deleted product' => [$editOrderPage, ['product-1', 'product-4']];

        $orderWithoutLineItems = new OrderEntity();
        $orderWithoutLineItems->setId('order-without-line-items');

        $orderPage = new AccountOrderPage();
        $orderPage->setOrders(new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            3,
            new OrderCollection([self::createOrder(['product-1']), self::createOrder(['product-3']), $orderWithoutLineItems]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        yield 'order history preloads the products of every order' => [$orderPage, ['product-1', 'product-3']];

        $overviewPage = new AccountOverviewPage();
        $overviewPage->setNewestOrder(self::createOrder(['product-3']));

        yield 'account overview preloads the newest order products' => [$overviewPage, ['product-3']];

        yield 'account overview without orders preloads nothing' => [new AccountOverviewPage(), []];

        yield 'render without a page parameter preloads nothing' => [null, []];
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
        ?GaranLabelTwigFilter $garanLabelTwigFilter = null,
    ): TemplateDataSubscriber {
        return new TemplateDataSubscriber(
            $hreflangLoader ?? $this->hreflangLoader,
            $shopIdProvider ?? $this->shopIdProvider,
            $activeAppsLoader ?? $this->activeAppsLoader,
            $themeRuntimeConfigService ?? $this->themeRuntimeConfigService,
            $garanLabelTwigFilter ?? $this->createGaranLabelTwigFilter(),
        );
    }

    private function createGaranLabelTwigFilter(?\Closure $search = null): GaranLabelTwigFilter
    {
        $productRepository = StaticEntityRepository::of(ProductCollection::class, $search === null ? [] : [$search], new ProductDefinition());
        $durationFormatter = new GaranLabelDurationFormatter();

        return new GaranLabelTwigFilter(
            $durationFormatter,
            $productRepository,
            new GaranLabelResolver($durationFormatter, new GaranLabelRenderer(static::createStub(Environment::class))),
            new GaranLabelInlineImage(),
        );
    }

    /**
     * @param list<string> $productIds
     */
    private static function createOrder(array $productIds): OrderEntity
    {
        $lineItems = new OrderLineItemCollection();

        foreach ($productIds as $productId) {
            $lineItem = new OrderLineItemEntity();
            $lineItem->setId(Uuid::randomHex());
            $lineItem->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
            $lineItem->setProductId($productId);
            $lineItem->setReferencedId($productId);
            $lineItems->add($lineItem);
        }

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setLineItems($lineItems);

        return $order;
    }
}
