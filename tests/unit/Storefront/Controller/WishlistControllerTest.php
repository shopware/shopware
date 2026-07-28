<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractAddWishlistProductRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoadWishlistRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractMergeWishlistProductRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRemoveWishlistProductRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LoadWishlistRouteResponse;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Storefront\Controller\WishlistController;
use Shopware\Storefront\Page\Wishlist\GuestWishlistPageLoader;
use Shopware\Storefront\Page\Wishlist\WishlistPageLoader;
use Shopware\Storefront\Page\Wishlist\WishListPageProductCriteriaEvent;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPageletLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WishlistController::class)]
class WishlistControllerTest extends TestCase
{
    private AbstractLoadWishlistRoute&MockObject $wishlistLoadRoute;

    private CollectingEventDispatcher $eventDispatcher;

    private WishlistController $controller;

    protected function setUp(): void
    {
        $this->wishlistLoadRoute = $this->createMock(AbstractLoadWishlistRoute::class);
        $this->eventDispatcher = new CollectingEventDispatcher();

        $this->controller = new WishlistController(
            static::createStub(WishlistPageLoader::class),
            $this->wishlistLoadRoute,
            static::createStub(AbstractAddWishlistProductRoute::class),
            static::createStub(AbstractRemoveWishlistProductRoute::class),
            static::createStub(AbstractMergeWishlistProductRoute::class),
            static::createStub(GuestWishlistPageLoader::class),
            static::createStub(GuestWishlistPageletLoader::class),
            $this->eventDispatcher,
        );
    }

    public function testAjaxListReturnsTheWishlistProductIds(): void
    {
        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());

        $response = new LoadWishlistRouteResponse(
            new CustomerWishlistEntity(),
            new EntitySearchResult(
                'product',
                1,
                new ProductCollection([$product]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        );

        $this->wishlistLoadRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($response);

        $result = $this->controller->ajaxList(new Request(), Generator::generateSalesChannelContext(), new CustomerEntity());

        $content = $result->getContent();
        static::assertIsString($content);
        static::assertSame([$product->getId() => $product->getId()], json_decode($content, true));

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(WishListPageProductCriteriaEvent::class, $events[0]);
    }

    public function testAjaxListReturnsAnEmptyListWhenTheCustomerHasNoWishlist(): void
    {
        $this->wishlistLoadRoute
            ->expects($this->once())
            ->method('load')
            ->willThrowException(new CustomerWishlistNotFoundException());

        $result = $this->controller->ajaxList(new Request(), Generator::generateSalesChannelContext(), new CustomerEntity());

        $content = $result->getContent();
        static::assertIsString($content);
        static::assertSame([], json_decode($content, true));
    }
}
