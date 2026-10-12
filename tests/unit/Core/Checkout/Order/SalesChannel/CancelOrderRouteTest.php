<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Extension\CancelOrderRouteExtension;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\CancelOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\CancelOrderRouteResponse;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CancelOrderRoute::class)]
class CancelOrderRouteTest extends TestCase
{
    public function testRefundsDisabled(): void
    {
        $this->expectExceptionObject(OrderException::orderNotCancellable());

        $route = new CancelOrderRoute(
            static::createStub(OrderService::class),
            static::createStub(EntityRepository::class),
            new StaticSystemConfigService([
                'core.cart.enableOrderRefunds' => false,
            ]),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $route->cancel(new Request(['orderId' => Uuid::randomHex()]), static::createStub(SalesChannelContext::class));
    }

    public function testNoOrderId(): void
    {
        $this->expectExceptionObject(OrderException::invalidRequestParameter('orderId'));

        $route = new CancelOrderRoute(
            static::createStub(OrderService::class),
            static::createStub(EntityRepository::class),
            new StaticSystemConfigService([
                'core.cart.enableOrderRefunds' => true,
            ]),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $route->cancel(new Request(), static::createStub(SalesChannelContext::class));
    }

    public function testNotLoggedIn(): void
    {
        $this->expectExceptionObject(OrderException::customerNotLoggedIn());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomer')
            ->willReturn(null);

        $route = new CancelOrderRoute(
            static::createStub(OrderService::class),
            static::createStub(EntityRepository::class),
            new StaticSystemConfigService([
                'core.cart.enableOrderRefunds' => true,
            ]),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $route->cancel(new Request([], ['orderId' => Uuid::randomHex()]), $salesChannelContext);
    }

    public function testOrderNotFound(): void
    {
        $this->expectException(OrderException::class);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomerId')
            ->willReturn($customer->getId());

        /** @var StaticEntityRepository<OrderCollection> */
        $orderRepository = new StaticEntityRepository([[]]);

        $route = new CancelOrderRoute(
            static::createStub(OrderService::class),
            $orderRepository,
            new StaticSystemConfigService([
                'core.cart.enableOrderRefunds' => true,
            ]),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $route->cancel(new Request([], ['orderId' => Uuid::randomHex()]), $salesChannelContext);
    }

    public function testCancelOrder(): void
    {
        $orderId = Uuid::randomHex();
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomerId')
            ->willReturn($customer->getId());
        $salesChannelContext
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $orderService = $this->createMock(OrderService::class);
        $orderService
            ->expects($this->once())
            ->method('orderStateTransition')
            ->with($orderId, 'cancel', new ParameterBag(), Context::createDefaultContext())
            ->willReturn(new StateMachineStateEntity());

        /** @var StaticEntityRepository<OrderCollection> */
        $orderRepository = new StaticEntityRepository([[Uuid::randomHex()]]);

        $route = new CancelOrderRoute(
            $orderService,
            $orderRepository,
            new StaticSystemConfigService([
                'core.cart.enableOrderRefunds' => true,
            ]),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $route->cancel(new Request([], ['orderId' => $orderId]), $salesChannelContext);
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $response = new CancelOrderRouteResponse(new StateMachineStateEntity());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('cancel-order-route.cancel.pre', static function (CancelOrderRouteExtension $extension) use ($request, $context, $response): void {
            static::assertSame(['request' => $request, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CancelOrderRoute(
            static::createStub(OrderService::class),
            static::createStub(EntityRepository::class),
            static::createStub(SystemConfigService::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->cancel($request, $context));
    }
}
