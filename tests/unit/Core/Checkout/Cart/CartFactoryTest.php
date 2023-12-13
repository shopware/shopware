<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartFactory;
use Shopware\Core\Checkout\Cart\Event\CartCreatedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(CartFactory::class)]
class CartFactoryTest extends TestCase
{
    public function testCreatesNewCart(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(CartCreatedEvent::class));

        $factory = new CartFactory($dispatcher);

        $cart = $factory->createNew('test');
        static::assertSame('test', $cart->getToken());
        static::assertNull($cart->getSource());
    }

    public function testCreatesNewCartWithSource(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(CartCreatedEvent::class));

        $factory = new CartFactory($dispatcher, 'source');

        $cart = $factory->createNew('test');
        static::assertSame('test', $cart->getToken());
        static::assertSame('source', $cart->getSource());
    }
}
