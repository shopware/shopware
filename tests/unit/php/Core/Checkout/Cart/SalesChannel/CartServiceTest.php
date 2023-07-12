<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartDeleteRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemRemoveRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemUpdateRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartLoadRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Cart\SalesChannel\CartService
 */
class CartServiceTest extends TestCase
{
    private AbstractCartDeleteRoute&MockObject $cartDeleteRoute;

    private AbstractCartItemUpdateRoute&MockObject $cartItemUpdateRoute;

    private AbstractCartItemRemoveRoute&MockObject $cartItemRemoveRoute;

    private CartService $cartService;

    protected function setUp(): void
    {
        $this->cartDeleteRoute = $this->createMock(AbstractCartDeleteRoute::class);
        $this->cartItemUpdateRoute = $this->createMock(AbstractCartItemUpdateRoute::class);
        $this->cartItemRemoveRoute = $this->createMock(AbstractCartItemRemoveRoute::class);

        $this->cartService = new CartService(
            $this->createMock(AbstractCartPersister::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CartCalculator::class),
            $this->createMock(AbstractCartLoadRoute::class),
            $this->cartDeleteRoute,
            $this->createMock(AbstractCartItemAddRoute::class),
            $this->cartItemUpdateRoute,
            $this->cartItemRemoveRoute,
            $this->createMock(AbstractCartOrderRoute::class),
        );
    }

    /**
     * @dataProvider providerCartServiceRoutes
     */
    public function testRouteCalled(
        string $routeName,
        string $routeMethodName,
        array $routeMethodArgumentsValidator,
        string $cartServiceMethod,
        array $cartServiceMethodArgs,
    ): void {
        $this->{$routeName}->expects(static::once())
            ->method($routeMethodName)
            ->with(...$routeMethodArgumentsValidator);

        $this->cartService->{$cartServiceMethod}(...$cartServiceMethodArgs);
    }

    public function providerCartServiceRoutes()
    {
        $context = $this->createMock(SalesChannelContext::class);
        yield 'testDeleteCartCallsDeleteRoute' => ['cartDeleteRoute', 'delete', [$context], 'deleteCart', [$context]];

        $cart = new Cart(Uuid::randomHex());

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $ids = [$id1, $id2];

        yield 'testRemoveItemsCallsRemoveRoute' => ['cartItemRemoveRoute', 'remove', [
            static::callback(function (Request $actualRequest) use ($ids) {
                static::assertEquals($ids, $actualRequest->request->all('ids'));

                return true;
            }),
            $cart,
            $context,
        ], 'removeItems', [$cart, $ids, $context]];

        yield 'testRemoveCallsRemoveRoute' => ['cartItemRemoveRoute', 'remove', [
            static::callback(function (Request $actualRequest) use ($id1) {
                static::assertEquals([$id1], $actualRequest->request->all('ids'));

                return true;
            }),
            $cart,
            $context,
        ], 'remove', [$cart, $id1, $context]];

        yield 'testChangeQuantityCallsItemUpdateRoute' => ['cartItemUpdateRoute', 'change', [
            static::callback(function (Request $actualRequest) use ($id1) {
                $items = $actualRequest->request->all('items');
                static::assertIsArray($items);
                static::assertCount(1, $items);
                static::assertEquals($id1, $items[0]['id']);
                static::assertEquals(5, $items[0]['quantity']);

                return true;
            }),
            $cart,
            $context,
        ], 'changeQuantity', [$cart, $id1, 5, $context]];

        $items = [
            [
                'id' => $id1,
                'quantity' => 123,
            ],
            [
                'id' => $id2,
                'quantity' => 234,
            ],
        ];

        yield 'testUpdateMethodCallsUpdateRoute' => ['cartItemUpdateRoute', 'change', [
            static::callback(function (Request $actualRequest) use ($items) {
                static::assertEquals($items, $actualRequest->request->all('items'));

                return true;
            }),
            $cart,
            $context,
        ], 'update', [$cart, $items, $context]];
    }
}
