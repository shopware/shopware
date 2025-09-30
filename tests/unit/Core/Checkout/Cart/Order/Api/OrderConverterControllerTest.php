<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Order\Api\OrderConverterController;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderConverterController::class)]
class OrderConverterControllerTest extends TestCase
{
    public function testOrderNotFoundException(): void
    {
        $orderId = Uuid::randomHex();
        $this->expectException(CartException::class);
        $this->expectExceptionMessage("Order $orderId could not be found.");

        $converter = $this->createMock(OrderConverter::class);
        $persister = $this->createMock(AbstractCartPersister::class);

        /** @var StaticEntityRepository<OrderCollection> */
        $orderRepository = new StaticEntityRepository([new OrderCollection([])]);

        $controller = new OrderConverterController($converter, $persister, $orderRepository);
        $controller->convertToCart($orderId, Context::createDefaultContext());
    }

    public function testOrderConvertToCart(): void
    {
        $converter = $this->createMock(OrderConverter::class);
        $persister = $this->createMock(AbstractCartPersister::class);

        $orderId = Uuid::randomHex();
        $order = new OrderEntity();
        $order->setId($orderId);

        $cart = new Cart('test');
        $converter
            ->expects($this->once())
            ->method('convertToCart')
            ->with($order)
            ->willReturn($cart);
        $converter
            ->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->willReturn($this->createMock(SalesChannelContext::class));

        $persister
            ->expects($this->once())
            ->method('save')
            ->with($cart);

        /** @var StaticEntityRepository<OrderCollection> */
        $orderRepository = new StaticEntityRepository([new OrderCollection([$order])]);

        $controller = new OrderConverterController($converter, $persister, $orderRepository);
        $response = $controller->convertToCart($orderId, Context::createDefaultContext());
        $data = json_decode((string) $response->getContent(), true);

        static::assertSame($cart->getToken(), $data['token']);
    }
}
