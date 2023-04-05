<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page;

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
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPagelet;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPageletLoader;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishListPageletProductCriteriaEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class GuestWishlistPageletTest extends TestCase
{
    use EventDispatcherBehaviour;

    /**
     * @var MockObject|ProductListRoute
     */
    private ProductListRoute $productListRouteMock;

    /**
     * @var MockObject|SystemConfigService
     */
    private SystemConfigService $systemConfigServiceMock;

    /**
     * @var MockObject|SalesChannelContext
     */
    private SalesChannelContext $salesChannelContextMock;

    private EventDispatcher $eventDispatcher;

    private AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory;

    protected function setUp(): void
    {
        $this->productListRouteMock = $this->createMock(ProductListRoute::class);
        $this->systemConfigServiceMock = $this->createMock(SystemConfigService::class);
        $this->salesChannelContextMock = $this->createMock(SalesChannelContext::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->productCloseoutFilterFactory = new ProductCloseoutFilterFactory();
    }

    public function testItThrowsExceptionWithInvalidProductIds(): void
    {
        static::expectException(\InvalidArgumentException::class);
        $request = new Request();

        $request->request->set('productIds', 'invalid value');

        $this->getPageLoader()->load($request, $this->salesChannelContextMock);
    }

    public function testItLoadsFilledPageletAndThrowsEvent(): void
    {
        $request = new Request();

        $request->attributes->set('productIds', [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()]);

        // Mocks the load function
        $productRouteLoadClosure = function (Criteria $criteria, SalesChannelContext $context): ProductListResponse {
            $product1 = new ProductEntity();
            $product1->setUniqueIdentifier($criteria->getIds()[0]); /** @phpstan-ignore-line */
            $product2 = new ProductEntity();
            $product2->setUniqueIdentifier($criteria->getIds()[1]); /** @phpstan-ignore-line */
            $product3 = new ProductEntity();
            $product3->setUniqueIdentifier($criteria->getIds()[2]); /** @phpstan-ignore-line */
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

        $this->productListRouteMock->expects(static::once())->method('load')->willReturnCallback($productRouteLoadClosure);

        $context = $this->salesChannelContextMock;

        $eventDidRun = null;
        $phpunit = $this;
        $listenerClosure = function (GuestWishlistPageletLoadedEvent $event) use (
            &$eventDidRun,
            $phpunit,
            $context,
            $request
        ): void {
            $eventDidRun = true;
            $phpunit->assertEquals($context, $event->getSalesChannelContext());
            $phpunit->assertEquals($request, $event->getRequest());
            $phpunit->assertEquals(3, $event->getPagelet()->getSearchResult()->getProducts()->count());
        };

        $this->addEventListener($this->eventDispatcher, GuestWishlistPageletLoadedEvent::class, $listenerClosure);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(GuestWishlistPagelet::class, $page);
        static::assertInstanceOf(ProductListResponse::class, $page->getSearchResult());
        $phpunit->assertEquals(3, $page->getSearchResult()->getProducts()->count());
        static::assertTrue($eventDidRun);
    }

    public function testCriteria(): void
    {
        $productId = Uuid::randomHex();
        $request = new Request();
        $request->attributes->set('productIds', [$productId]);

        $context = $this->salesChannelContextMock;

        $this->systemConfigServiceMock->expects(static::once())->method('getBool')
            ->with('core.listing.hideCloseoutProductsWhenOutOfStock')->willReturn(true);

        $eventDidRun = null;
        $phpunit = $this;
        $listenerClosure = function (GuestWishListPageletProductCriteriaEvent $event) use (
            &$eventDidRun,
            $phpunit,
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

            $phpunit->assertEquals($expectedCriteria, $event->getCriteria());
        };

        $this->addEventListener($this->eventDispatcher, GuestWishListPageletProductCriteriaEvent::class, $listenerClosure);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(GuestWishlistPagelet::class, $page);
        static::assertTrue($eventDidRun);
    }

    public function testItLoadsEmptyPagelet(): void
    {
        $request = new Request();

        $request->attributes->set('productIds', []);

        $context = $this->salesChannelContextMock;

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(GuestWishlistPagelet::class, $page);
        static::assertInstanceOf(ProductListResponse::class, $page->getSearchResult());
        static::assertEquals(0, $page->getSearchResult()->getProducts()->count());
    }

    /**
     * @return GuestWishlistPageletLoader
     */
    protected function getPageLoader()
    {
        return new GuestWishlistPageletLoader(
            $this->productListRouteMock,
            $this->systemConfigServiceMock,
            $this->eventDispatcher,
            $this->productCloseoutFilterFactory
        );
    }
}
