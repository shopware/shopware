<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SetPaymentOrderRoute::class)]
class SetPaymentOrderRouteTest extends TestCase
{
    #[DataProvider('requestDataProvider')]
    public function testInvalidRequest(Request $request): void
    {
        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('Invalid UUID provided:');

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $this->createMock(OrderService::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(OrderConverter::class),
            $this->createMock(CartRuleLoader::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(InitialStateIdLoader::class),
            $this->createMock(AbstractCheckoutGatewayRoute::class)
        );

        $paymentOrderRoute->setPayment($request, $this->createMock(SalesChannelContext::class));
    }

    public function testOrderNotFound(): void
    {
        $this->expectException(OrderException::class);

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $this->createMock(OrderService::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(OrderConverter::class),
            $this->createMock(CartRuleLoader::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(InitialStateIdLoader::class),
            $this->createMock(AbstractCheckoutGatewayRoute::class)
        );

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);

        $request = self::getRequest(['paymentMethodId' => Uuid::randomHex(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $salesChannelContext);
    }

    public function testInvalidPaymentMethod(): void
    {
        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('The payment method with id');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        /** @var StaticEntityRepository<OrderCollection> $staticRepository */
        $staticRepository = new StaticEntityRepository([new OrderCollection([$order])], new OrderDefinition());

        $gatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $gatewayRoute
            ->expects($this->once())
            ->method('load');

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $this->createMock(OrderService::class),
            $staticRepository,
            $this->createMock(OrderConverter::class),
            $this->createMock(CartRuleLoader::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(InitialStateIdLoader::class),
            $gatewayRoute
        );

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);

        $request = self::getRequest(['paymentMethodId' => Uuid::randomHex(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $salesChannelContext);
    }

    public function testPaymentNotChangeable(): void
    {
        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('Payment methods of order with current payment transaction type can not be changed.');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        /** @var StaticEntityRepository<OrderCollection> $staticRepository */
        $staticRepository = new StaticEntityRepository([new OrderCollection([$order])], new OrderDefinition());

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());
        $response = new CheckoutGatewayRouteResponse(
            new PaymentMethodCollection([$paymentMethod]),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $gatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $gatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($response);

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $this->createMock(OrderService::class),
            $staticRepository,
            $this->createMock(OrderConverter::class),
            $this->createMock(CartRuleLoader::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(InitialStateIdLoader::class),
            $gatewayRoute
        );

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);

        $request = self::getRequest(['paymentMethodId' => $paymentMethod->getId(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $salesChannelContext);
    }

    public function testReopenAndCancelTransactions(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());

        $transactionState = new OrderTransactionEntity();
        $transactionState->setId(Uuid::randomHex());
        $transactionState->setPaymentMethodId(Uuid::randomHex());
        $transactionState->setStateId(Uuid::randomHex());
        $transactionStateLast = new OrderTransactionEntity();
        $transactionStateLast->setId(Uuid::randomHex());
        $transactionStateLast->setPaymentMethodId($paymentMethod->getId());
        $transactionStateLast->setStateId(Uuid::randomHex());

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setTransactions(new OrderTransactionCollection([$transactionState, $transactionStateLast]));

        /** @var StaticEntityRepository<OrderCollection> $staticRepository */
        $staticRepository = new StaticEntityRepository([new OrderCollection([$order])], new OrderDefinition());

        $response = new CheckoutGatewayRouteResponse(
            new PaymentMethodCollection([$paymentMethod]),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $gatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $gatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($response);

        $orderService = $this->createMock(OrderService::class);
        $orderService
            ->expects($this->once())
            ->method('isPaymentChangeableByTransactionState')
            ->willReturn(true);
        $orderService
            ->expects($this->exactly(2))
            ->method('orderTransactionStateTransition');

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $context = Generator::generateSalesChannelContext(customer: $customer);

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->willReturn($context);

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $orderService,
            $staticRepository,
            $orderConverter,
            $this->createMock(CartRuleLoader::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(InitialStateIdLoader::class),
            $gatewayRoute
        );

        $request = self::getRequest(['paymentMethodId' => $paymentMethod->getId(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $context);
    }

    public function testSetPaymentMethod(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());

        $price = new CartPrice(
            100,
            100,
            100,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_FREE
        );

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setPrice($price);

        $orderLater = new OrderEntity();
        $orderLater->setId(Uuid::randomHex());

        new EntitySearchResult(
            'order',
            1,
            new OrderCollection([$order]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult(
                    'order',
                    1,
                    new OrderCollection([$order]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext(),
                ),
                new EntitySearchResult(
                    'order',
                    1,
                    new OrderCollection([$orderLater]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext(),
                )
            );

        $orderRepository
            ->expects($this->once())
            ->method('update')
            ->willReturnCallback(function ($payload) use ($orderLater): EntityWrittenContainerEvent {
                static::assertCount(1, $payload);
                static::assertCount(1, $payload[0]['transactions']);

                $transactionState = new OrderTransactionEntity();
                $transactionState->setId($payload[0]['transactions'][0]['id']);

                $orderLater->setTransactions(new OrderTransactionCollection([$transactionState]));

                return new EntityWrittenContainerEvent(
                    Context::createDefaultContext(),
                    new NestedEventCollection(),
                    []
                );
            });

        $response = new CheckoutGatewayRouteResponse(
            new PaymentMethodCollection([$paymentMethod]),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $gatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $gatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($response);

        $orderService = $this->createMock(OrderService::class);
        $orderService
            ->expects($this->once())
            ->method('isPaymentChangeableByTransactionState')
            ->willReturn(true);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $context = Generator::generateSalesChannelContext(customer: $customer);

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->willReturn($context);

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $orderService,
            $orderRepository,
            $orderConverter,
            $this->createMock(CartRuleLoader::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(InitialStateIdLoader::class),
            $gatewayRoute
        );

        $request = self::getRequest(['paymentMethodId' => $paymentMethod->getId(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $context);
    }

    /**
     * @return array<string, Request[]>
     */
    public static function requestDataProvider(): array
    {
        return [
            'empty' => [
                self::getRequest([]),
            ],
            'invalid payment method' => [
                self::getRequest(['paymentMethodId' => 'some payment method id']),
            ],
            'invalid order' => [
                self::getRequest(['paymentMethodId' => Uuid::randomHex(), 'orderId' => 'some order id']),
            ],
        ];
    }

    /**
     * @param array<string, true|string> $attributes
     */
    private static function getRequest(array $attributes): Request
    {
        $request = Request::create($_SERVER['APP_URL'], Request::METHOD_GET);

        foreach ($attributes as $key => $attribute) {
            $request->request->set($key, $attribute);
        }

        return $request;
    }
}
