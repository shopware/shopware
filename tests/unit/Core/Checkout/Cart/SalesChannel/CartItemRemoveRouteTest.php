<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartLocker;
use Shopware\Core\Checkout\Cart\Extension\CartItemRemoveRouteExtension;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartItemRemoveRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartResponse;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartItemRemoveRoute::class)]
class CartItemRemoveRouteTest extends TestCase
{
    public function testRouteUsesLock(): void
    {
        $cartLocker = $this->createMock(CartLocker::class);
        $cartLocker
            ->expects($this->once())
            ->method('locked')
            ->willReturnCallback(static fn (SalesChannelContext $context, \Closure $closure) => $closure());

        $cart = new Cart('test');
        $lineItem = new LineItem('test', 'test');
        $lineItem->setRemovable(true);
        $cart->add($lineItem);

        $persister = $this->createMock(AbstractCartPersister::class);
        $persister
            ->expects($this->once())
            ->method('save');

        $route = new CartItemRemoveRoute(
            static::createStub(EventDispatcherInterface::class),
            static::createStub(CartCalculator::class),
            $persister,
            $cartLocker,
            new ExtensionDispatcher(new EventDispatcher())
        );

        $route->remove(
            new Request(['ids' => ['test']]),
            $cart,
            static::createStub(SalesChannelContext::class)
        );
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $cart = new Cart(Uuid::randomHex());
        $context = Generator::generateSalesChannelContext();
        $response = new CartResponse(new Cart('token'));

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('cart-item-remove-route.remove.pre', static function (CartItemRemoveRouteExtension $extension) use ($request, $cart, $context, $response): void {
            static::assertSame(['request' => $request, 'cart' => $cart, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CartItemRemoveRoute(
            static::createStub(EventDispatcherInterface::class),
            static::createStub(CartCalculator::class),
            static::createStub(AbstractCartPersister::class),
            static::createStub(CartLocker::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->remove($request, $cart, $context));
    }
}
