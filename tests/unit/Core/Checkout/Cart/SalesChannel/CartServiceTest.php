<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartFactory;
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
 */
#[CoversClass(CartService::class)]
class CartServiceTest extends TestCase
{
    private AbstractCartDeleteRoute&MockObject $cartDeleteRoute;

    private AbstractCartItemUpdateRoute&MockObject $cartItemUpdateRoute;

    private AbstractCartItemRemoveRoute&MockObject $cartItemRemoveRoute;

    private CartFactory&MockObject $cartFactory;

    private CartService $cartService;

    protected function setUp(): void
    {
        $this->cartDeleteRoute = $this->createMock(AbstractCartDeleteRoute::class);
        $this->cartItemUpdateRoute = $this->createMock(AbstractCartItemUpdateRoute::class);
        $this->cartItemRemoveRoute = $this->createMock(AbstractCartItemRemoveRoute::class);
        $this->cartFactory = $this->createMock(CartFactory::class);

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
            $this->cartFactory,
        );
    }

    public function testDeleteCartCallsDeleteRoute(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $this->cartDeleteRoute->expects(static::once())
            ->method('delete')
            ->with($context)
        ;

        $this->cartService->deleteCart($context);
    }

    public function testRemoveItemsCallsRemoveRoute(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $cart = new Cart(Uuid::randomHex());

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $ids = [$id1, $id2];

        $this->cartItemRemoveRoute->expects(static::once())
            ->method('remove')
            ->with(static::callback(function (Request $actualRequest) use ($ids) {
                static::assertEquals($ids, $actualRequest->request->all('ids'));

                return true;
            }), $cart, $context);

        $this->cartService->removeItems($cart, $ids, $context);
    }

    public function testRemoveCallsRemoveRoute(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $cart = new Cart(Uuid::randomHex());

        $id = Uuid::randomHex();

        $this->cartItemRemoveRoute->expects(static::once())
            ->method('remove')
            ->with(static::callback(function (Request $actualRequest) use ($id) {
                static::assertEquals([$id], $actualRequest->request->all('ids'));

                return true;
            }), $cart, $context);

        $this->cartService->remove($cart, $id, $context);
    }

    public function testChangeQuantityCallsItemUpdateRoute(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $cart = new Cart(Uuid::randomHex());

        $id = Uuid::randomHex();

        $this->cartItemUpdateRoute->expects(static::once())
            ->method('change')
            ->with(static::callback(function (Request $actualRequest) use ($id) {
                $items = $actualRequest->request->all('items');
                static::assertCount(1, $items);
                static::assertEquals($id, $items[0]['id']);
                static::assertEquals(5, $items[0]['quantity']);

                return true;
            }), $cart, $context);

        $this->cartService->changeQuantity($cart, $id, 5, $context);
    }

    public function testUpdateMethodCallsUpdateRoute(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $cart = new Cart(Uuid::randomHex());

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
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

        $this->cartItemUpdateRoute->expects(static::once())
            ->method('change')
            ->with(static::callback(function (Request $actualRequest) use ($items) {
                static::assertEquals($items, $actualRequest->request->all('items'));

                return true;
            }), $cart, $context);

        $this->cartService->update($cart, $items, $context);
    }

    public function testCreatesNewCart(): void
    {
        $cart = new Cart('test');
        $this->cartFactory
            ->expects(static::once())
            ->method('createNew')
            ->with('test')
            ->willReturn($cart);

        static::assertSame($cart, $this->cartService->createNew('test'));
    }
}
