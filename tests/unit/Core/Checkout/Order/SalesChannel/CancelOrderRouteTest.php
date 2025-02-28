<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\CancelOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CancelOrderRoute::class)]
class CancelOrderRouteTest extends TestCase
{
    public function testNoOrderId(): void
    {
        $this->expectException(OrderException::class);

        $route = new CancelOrderRoute(
            $this->createMock(OrderService::class),
            $this->createMock(EntityRepository::class)
        );

        $route->cancel(new Request(), $this->createMock(SalesChannelContext::class));
    }

    public function testNotLoggedIn(): void
    {
        $this->expectException(OrderException::class);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects(static::once())
            ->method('getCustomer')
            ->willReturn(null);

        $route = new CancelOrderRoute(
            $this->createMock(OrderService::class),
            $this->createMock(EntityRepository::class)
        );

        $route->cancel(new Request(['orderId' => Uuid::randomHex()]), $salesChannelContext);
    }

    public function testOrderNotFound(): void
    {
        $this->expectException(OrderException::class);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects(static::once())
            ->method('getCustomer')
            ->willReturn($customer);
        $salesChannelContext
            ->expects(static::once())
            ->method('getCustomerId')
            ->willReturn($customer->getId());

        /** @var StaticEntityRepository<OrderCollection> */
        $orderRepository = new StaticEntityRepository([[]]);

        $route = new CancelOrderRoute($this->createMock(OrderService::class), $orderRepository);

        $route->cancel(new Request(['orderId' => Uuid::randomHex()]), $salesChannelContext);
    }

    public function testCancelOrder(): void
    {
        $orderId = Uuid::randomHex();
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects(static::once())
            ->method('getCustomer')
            ->willReturn($customer);
        $salesChannelContext
            ->expects(static::once())
            ->method('getCustomerId')
            ->willReturn($customer->getId());
        $salesChannelContext
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $orderService = $this->createMock(OrderService::class);
        $orderService
            ->expects(static::once())
            ->method('orderStateTransition')
            ->with($orderId, 'cancel', new ParameterBag(), Context::createDefaultContext())
            ->willReturn(new StateMachineStateEntity());

        /** @var StaticEntityRepository<OrderCollection> */
        $orderRepository = new StaticEntityRepository([[Uuid::randomHex()]]);

        $route = new CancelOrderRoute($orderService, $orderRepository);

        $route->cancel(new Request(['orderId' => $orderId]), $salesChannelContext);
    }
}
