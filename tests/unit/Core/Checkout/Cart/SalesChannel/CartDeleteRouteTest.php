<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\CartLocker;
use Shopware\Core\Checkout\Cart\Extension\CartDeleteRouteExtension;
use Shopware\Core\Checkout\Cart\SalesChannel\CartDeleteRoute;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartDeleteRoute::class)]
class CartDeleteRouteTest extends TestCase
{
    public function testRouteUsesLock(): void
    {
        $cartLocker = $this->createMock(CartLocker::class);
        $cartLocker
            ->expects($this->once())
            ->method('locked')
            ->willReturnCallback(static fn (SalesChannelContext $context, \Closure $closure) => $closure());

        $persister = $this->createMock(AbstractCartPersister::class);
        $persister
            ->expects($this->once())
            ->method('delete');

        $route = new CartDeleteRoute(
            $persister,
            static::createStub(EventDispatcherInterface::class),
            $cartLocker,
            new ExtensionDispatcher(new EventDispatcher())
        );

        $route->delete(
            static::createStub(SalesChannelContext::class)
        );
    }

    public function testPublishesExtension(): void
    {
        $context = Generator::generateSalesChannelContext();
        $response = new NoContentResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('cart-delete-route.delete.pre', static function (CartDeleteRouteExtension $extension) use ($context, $response): void {
            static::assertSame(['context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CartDeleteRoute(
            static::createStub(AbstractCartPersister::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(CartLocker::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->delete($context));
    }
}
