<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartService::class)]
class CartServiceTest extends TestCase
{
    private AbstractCartDeleteRoute&Stub $cartDeleteRoute;

    private AbstractCartItemUpdateRoute&Stub $cartItemUpdateRoute;

    private AbstractCartItemRemoveRoute&Stub $cartItemRemoveRoute;

    private CartFactory&Stub $cartFactory;

    protected function setUp(): void
    {
        $this->cartDeleteRoute = static::createStub(AbstractCartDeleteRoute::class);
        $this->cartItemUpdateRoute = static::createStub(AbstractCartItemUpdateRoute::class);
        $this->cartItemRemoveRoute = static::createStub(AbstractCartItemRemoveRoute::class);
        $this->cartFactory = static::createStub(CartFactory::class);
    }

    public function testDeleteCartCallsDeleteRoute(): void
    {
        $context = static::createStub(SalesChannelContext::class);

        $cartDeleteRoute = $this->createMock(AbstractCartDeleteRoute::class);
        $cartDeleteRoute->expects($this->once())
            ->method('delete')
            ->with($context)
        ;

        $cartService = $this->buildCartService(cartDeleteRoute: $cartDeleteRoute);

        $cartService->deleteCart($context);
    }

    public function testRemoveItemsCallsRemoveRoute(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $cart = new Cart(Uuid::randomHex());

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $ids = [$id1, $id2];

        $cartItemRemoveRoute = $this->createMock(AbstractCartItemRemoveRoute::class);
        $cartItemRemoveRoute->expects($this->once())
            ->method('remove')
            ->with(static::callback(static function (Request $actualRequest) use ($ids) {
                static::assertSame($ids, $actualRequest->request->all('ids'));

                return true;
            }), $cart, $context);

        $cartService = $this->buildCartService(cartItemRemoveRoute: $cartItemRemoveRoute);

        $cartService->removeItems($cart, $ids, $context);
    }

    public function testRemoveCallsRemoveRoute(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $cart = new Cart(Uuid::randomHex());

        $id = Uuid::randomHex();

        $cartItemRemoveRoute = $this->createMock(AbstractCartItemRemoveRoute::class);
        $cartItemRemoveRoute->expects($this->once())
            ->method('remove')
            ->with(static::callback(static function (Request $actualRequest) use ($id) {
                static::assertSame([$id], $actualRequest->request->all('ids'));

                return true;
            }), $cart, $context);

        $cartService = $this->buildCartService(cartItemRemoveRoute: $cartItemRemoveRoute);

        $cartService->remove($cart, $id, $context);
    }

    public function testChangeQuantityCallsItemUpdateRoute(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $cart = new Cart(Uuid::randomHex());

        $id = Uuid::randomHex();

        $cartItemUpdateRoute = $this->createMock(AbstractCartItemUpdateRoute::class);
        $cartItemUpdateRoute->expects($this->once())
            ->method('change')
            ->with(static::callback(static function (Request $actualRequest) use ($id) {
                $items = $actualRequest->request->all('items');
                static::assertCount(1, $items);
                static::assertSame($id, $items[0]['id']);
                static::assertSame(5, $items[0]['quantity']);

                return true;
            }), $cart, $context);

        $cartService = $this->buildCartService(cartItemUpdateRoute: $cartItemUpdateRoute);

        $cartService->changeQuantity($cart, $id, 5, $context);
    }

    public function testUpdateMethodCallsUpdateRoute(): void
    {
        $context = static::createStub(SalesChannelContext::class);
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

        $cartItemUpdateRoute = $this->createMock(AbstractCartItemUpdateRoute::class);
        $cartItemUpdateRoute->expects($this->once())
            ->method('change')
            ->with(static::callback(static function (Request $actualRequest) use ($items) {
                static::assertSame($items, $actualRequest->request->all('items'));

                return true;
            }), $cart, $context);

        $cartService = $this->buildCartService(cartItemUpdateRoute: $cartItemUpdateRoute);

        $cartService->update($cart, $items, $context);
    }

    public function testCreatesNewCart(): void
    {
        $cart = new Cart('test');

        $cartFactory = $this->createMock(CartFactory::class);
        $cartFactory
            ->expects($this->once())
            ->method('createNew')
            ->with('test')
            ->willReturn($cart);

        $cartService = $this->buildCartService(cartFactory: $cartFactory);

        static::assertSame($cart, $cartService->createNew('test'));
    }

    private function buildCartService(
        ?AbstractCartDeleteRoute $cartDeleteRoute = null,
        ?AbstractCartItemUpdateRoute $cartItemUpdateRoute = null,
        ?AbstractCartItemRemoveRoute $cartItemRemoveRoute = null,
        ?CartFactory $cartFactory = null,
    ): CartService {
        return new CartService(
            static::createStub(AbstractCartPersister::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(CartCalculator::class),
            static::createStub(AbstractCartLoadRoute::class),
            $cartDeleteRoute ?? $this->cartDeleteRoute,
            static::createStub(AbstractCartItemAddRoute::class),
            $cartItemUpdateRoute ?? $this->cartItemUpdateRoute,
            $cartItemRemoveRoute ?? $this->cartItemRemoveRoute,
            static::createStub(AbstractCartOrderRoute::class),
            $cartFactory ?? $this->cartFactory,
        );
    }
}
