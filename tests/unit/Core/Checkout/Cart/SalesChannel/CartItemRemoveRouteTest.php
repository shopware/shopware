<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartLocker;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartItemRemoveRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
            ->willReturnCallback(fn (string $token, \Closure $closure) => $closure());

        $cart = new Cart('test');
        $lineItem = new LineItem('test', 'test');
        $lineItem->setRemovable(true);
        $cart->add($lineItem);

        $persister = $this->createMock(AbstractCartPersister::class);
        $persister
            ->expects($this->once())
            ->method('save');

        $route = new CartItemRemoveRoute(
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(CartCalculator::class),
            $persister,
            $cartLocker
        );

        $route->remove(
            new Request(['ids' => ['test']]),
            $cart,
            $this->createMock(SalesChannelContext::class)
        );
    }
}
