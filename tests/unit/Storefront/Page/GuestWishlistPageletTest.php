<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\ProductListResponse;
use Shopware\Core\Content\Product\SalesChannel\ProductListRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPageletLoader;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishListPageletProductCriteriaEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(GuestWishlistPageletLoader::class)]
class GuestWishlistPageletTest extends TestCase
{
    use EventDispatcherBehaviour;

    private ProductListRoute&MockObject $productListRouteMock;

    private SystemConfigService $systemConfigServiceStub;

    private SalesChannelContext $salesChannelContextMock;

    private EventDispatcher $eventDispatcher;

    private AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory;

    protected function setUp(): void
    {
        $this->productListRouteMock = $this->createMock(ProductListRoute::class);
        $this->systemConfigServiceStub = new StaticSystemConfigService([
            'core.listing.hideCloseoutProductsWhenOutOfStock' => true,
        ]);
        $this->salesChannelContextMock = $this->createMock(SalesChannelContext::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->productCloseoutFilterFactory = new ProductCloseoutFilterFactory();
    }

    public function testItThrowsExceptionWithInvalidProductIds(): void
    {
        $this->expectException(RoutingException::class);

        $request = new Request();

        $request->request->set('productIds', 'invalid value');

        $this->getPageLoader()->load($request, $this->salesChannelContextMock);
    }

    public function testItLoadsFilledPageletAndThrowsEvent(): void
    {
        $request = new Request();

        $request->attributes->set('productIds', [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()]);

        // Mocks the load function
        $productRouteLoadClosure = static function (Criteria $criteria, SalesChannelContext $context): ProductListResponse {
            $product1 = new ProductEntity();
            static::assertIsString($criteria->getIds()[0]);
            $product1->setUniqueIdentifier($criteria->getIds()[0]);
            $product2 = new ProductEntity();
            static::assertIsString($criteria->getIds()[1]);
            $product2->setUniqueIdentifier($criteria->getIds()[1]);
            $product3 = new ProductEntity();
            static::assertIsString($criteria->getIds()[2]);
            $product3->setUniqueIdentifier($criteria->getIds()[2]);
            $searchResult = new EntitySearchResult(
                'product',
                3,
                new ProductCollection([$product1, $product2, $product3]),
                null,
                $criteria,
                $context->getContext()
            );

            return new ProductListResponse($searchResult);
        };

        $this->productListRouteMock->expects($this->once())->method('load')->willReturnCallback($productRouteLoadClosure);

        $context = $this->salesChannelContextMock;

        $eventDidRun = null;
        $listenerClosure = static function (GuestWishlistPageletLoadedEvent $event) use (
            &$eventDidRun,
            $context,
            $request
        ): void {
            $eventDidRun = true;
            static::assertSame($context, $event->getSalesChannelContext());
            static::assertSame($request, $event->getRequest());
            static::assertCount(3, $event->getPagelet()->getSearchResult()->getProducts());
        };

        $this->addEventListener($this->eventDispatcher, GuestWishlistPageletLoadedEvent::class, $listenerClosure);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertCount(3, $page->getSearchResult()->getProducts());
        static::assertTrue($eventDidRun);
    }

    public function testCriteria(): void
    {
        $productId = Uuid::randomHex();
        $request = new Request();
        $request->attributes->set('productIds', [$productId]);

        $context = $this->salesChannelContextMock;

        $eventDidRun = null;
        $listenerClosure = function (GuestWishListPageletProductCriteriaEvent $event) use (
            &$eventDidRun,
            $productId,
            $context
        ): void {
            $eventDidRun = true;
            $expectedCriteria = new Criteria();
            $expectedCriteria->setLimit(100);
            $expectedCriteria->setIds([$productId]);
            $expectedCriteria->addAssociation('manufacturer')
                ->addAssociation('options.group')
                ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

            $filter = $this->productCloseoutFilterFactory->create($context);
            $expectedCriteria->addFilter($filter);

            static::assertEquals($expectedCriteria, $event->getCriteria());
        };

        $this->addEventListener($this->eventDispatcher, GuestWishListPageletProductCriteriaEvent::class, $listenerClosure);

        $this->getPageLoader()->load($request, $context);

        static::assertTrue($eventDidRun);
    }

    public function testItLoadsEmptyPagelet(): void
    {
        $request = new Request();

        $request->attributes->set('productIds', []);

        $page = $this->getPageLoader()->load($request, $this->salesChannelContextMock);

        static::assertCount(0, $page->getSearchResult()->getProducts());
    }

    private function getPageLoader(): GuestWishlistPageletLoader
    {
        return new GuestWishlistPageletLoader(
            $this->productListRouteMock,
            $this->systemConfigServiceStub,
            $this->eventDispatcher,
            $this->productCloseoutFilterFactory
        );
    }
}
