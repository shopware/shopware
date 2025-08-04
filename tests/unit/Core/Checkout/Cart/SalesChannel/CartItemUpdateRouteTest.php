<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartLocker;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartItemUpdateRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartItemUpdateRoute::class)]
class CartItemUpdateRouteTest extends TestCase
{
    public function testRouteUsesLock(): void
    {
        $cartLocker = $this->createMock(CartLocker::class);
        $cartLocker
            ->expects($this->once())
            ->method('locked')
            ->willReturnCallback(fn (string $token, \Closure $closure) => $closure());

        $lineItemFactory = $this->createMock(LineItemFactoryRegistry::class);
        $lineItemFactory
            ->expects($this->once())
            ->method('update');

        $route = new CartItemUpdateRoute(
            $this->createMock(AbstractCartPersister::class),
            $this->createMock(CartCalculator::class),
            $lineItemFactory,
            $this->createMock(EventDispatcherInterface::class),
            $cartLocker
        );

        $route->change(
            new Request([], ['items' => [['id' => 'test', 'quantity' => 2]]]),
            new Cart('test'),
            $this->createMock(SalesChannelContext::class)
        );
    }
}
