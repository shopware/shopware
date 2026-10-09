<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderRestorer;
use Shopware\Core\Checkout\Cart\Order\RestoredOrder;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\RuleLoaderResult;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Event\OrderPaymentMethodChangedCriteriaEvent;
use Shopware\Core\Checkout\Order\Extension\SetPaymentOrderRouteExtension;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRouteResponse;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
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
        $this->expectExceptionObject(OrderException::invalidUuid(''));

        $paymentOrderRoute = new SetPaymentOrderRoute(
            static::createStub(OrderService::class),
            static::createStub(EntityRepository::class),
            static::createStub(OrderConverter::class),
            static::createStub(CartRuleLoader::class),
            static::createStub(OrderRestorer::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            static::createStub(AbstractCheckoutGatewayRoute::class),
            new ExtensionDispatcher(new EventDispatcher())
        );

        $paymentOrderRoute->setPayment($request, static::createStub(SalesChannelContext::class));
    }

    public function testOrderNotFound(): void
    {
        $this->expectException(OrderException::class);

        $paymentOrderRoute = new SetPaymentOrderRoute(
            static::createStub(OrderService::class),
            static::createStub(EntityRepository::class),
            static::createStub(OrderConverter::class),
            static::createStub(CartRuleLoader::class),
            static::createStub(OrderRestorer::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            static::createStub(AbstractCheckoutGatewayRoute::class),
            new ExtensionDispatcher(new EventDispatcher())
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
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $staticRepository = new StaticEntityRepository([new OrderCollection([$order])], new OrderDefinition());

        $gatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $gatewayRoute
            ->expects($this->once())
            ->method('load');

        $orderRestorer = static::createStub(OrderRestorer::class);
        $orderRestorer
            ->method('restore')
            ->willReturn(new RestoredOrder($order, Generator::generateSalesChannelContext(), new Cart('restored-token')));

        $paymentOrderRoute = new SetPaymentOrderRoute(
            static::createStub(OrderService::class),
            $staticRepository,
            static::createStub(OrderConverter::class),
            static::createStub(CartRuleLoader::class),
            $orderRestorer,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            $gatewayRoute,
            new ExtensionDispatcher(new EventDispatcher())
        );

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);

        $paymentMethodId = Uuid::randomHex();
        $request = self::getRequest(['paymentMethodId' => $paymentMethodId, 'orderId' => Uuid::randomHex()]);

        $this->expectExceptionObject(OrderException::paymentMethodNotAvailable($paymentMethodId));

        $paymentOrderRoute->setPayment($request, $salesChannelContext);
    }

    public function testPaymentNotChangeable(): void
    {
        $this->expectExceptionObject(OrderException::paymentMethodNotChangeable());

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $staticRepository = new StaticEntityRepository([new OrderCollection([$order])], new OrderDefinition());

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());
        $paymentMethod->setAfterOrderEnabled(true);
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

        $orderRestorer = static::createStub(OrderRestorer::class);
        $orderRestorer
            ->method('restore')
            ->willReturn(new RestoredOrder($order, Generator::generateSalesChannelContext(), new Cart('restored-token')));

        $paymentOrderRoute = new SetPaymentOrderRoute(
            static::createStub(OrderService::class),
            $staticRepository,
            static::createStub(OrderConverter::class),
            static::createStub(CartRuleLoader::class),
            $orderRestorer,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            $gatewayRoute,
            new ExtensionDispatcher(new EventDispatcher())
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

    public function testPaymentMethodNotAfterOrderEnabled(): void
    {
        $this->expectExceptionObject(OrderException::paymentMethodNotChangeable());

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $staticRepository = new StaticEntityRepository([new OrderCollection([$order])], new OrderDefinition());

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());
        $paymentMethod->setAfterOrderEnabled(false);
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
        // afterOrderEnabled is enforced before the transaction-state check, so it must not be consulted.
        $orderService
            ->expects($this->never())
            ->method('isPaymentChangeableByTransactionState');

        $orderRestorer = static::createStub(OrderRestorer::class);
        $orderRestorer
            ->method('restore')
            ->willReturn(new RestoredOrder($order, Generator::generateSalesChannelContext(), new Cart('restored-token')));

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $orderService,
            $staticRepository,
            static::createStub(OrderConverter::class),
            static::createStub(CartRuleLoader::class),
            $orderRestorer,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            $gatewayRoute,
            new ExtensionDispatcher(new EventDispatcher())
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
        $paymentMethod->setAfterOrderEnabled(true);

        $transactionState = new OrderTransactionEntity();
        $transactionState->setId(Uuid::randomHex());
        $transactionState->setPaymentMethodId(Uuid::randomHex());
        $transactionState->setStateId(Uuid::randomHex());
        $transactionState->setAmount(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $transactionStateLastId = Uuid::randomHex();
        $transactionStateLast = new OrderTransactionEntity();
        $transactionStateLast->setId($transactionStateLastId);
        $transactionStateLast->setPaymentMethodId($paymentMethod->getId());
        $transactionStateLast->setStateId(Uuid::randomHex());
        $transactionStateLast->setAmount(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setPrimaryOrderTransactionId($transactionStateLastId);
        $order->setPrimaryOrderTransaction($transactionStateLast);
        $order->setTransactions(new OrderTransactionCollection([$transactionState, $transactionStateLast]));
        $order->setPrice(new CartPrice(100, 100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_FREE));

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

        $orderRestorer = $this->createMock(OrderRestorer::class);
        $orderRestorer
            ->expects($this->once())
            ->method('restore')
            ->willReturn(new RestoredOrder($order, $context, new Cart('restored-token')));

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $orderService,
            $staticRepository,
            static::createStub(OrderConverter::class),
            static::createStub(CartRuleLoader::class),
            $orderRestorer,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            $gatewayRoute,
            new ExtensionDispatcher(new EventDispatcher())
        );

        $request = self::getRequest(['paymentMethodId' => $paymentMethod->getId(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $context);
    }

    public function testSetPaymentMethod(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());
        $paymentMethod->setAfterOrderEnabled(true);

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
            ->willReturnCallback(static function ($payload) use ($orderLater): EntityWrittenContainerEvent {
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

        $orderService = $this->createMock(OrderService::class);
        $orderService
            ->expects($this->once())
            ->method('isPaymentChangeableByTransactionState')
            ->willReturn(true);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $context = Generator::generateSalesChannelContext(customer: $customer);
        $restored = new RestoredOrder($order, Generator::generateSalesChannelContext(customer: $customer), new Cart('restored-token'));

        $gatewayRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static fn (Request $request): bool => $request->attributes->getAlnum('orderId') === $order->getId()),
                static::identicalTo($restored->cart),
                static::identicalTo($restored->context),
            )
            ->willReturn($response);

        $orderRestorer = $this->createMock(OrderRestorer::class);
        $orderRestorer
            ->expects($this->once())
            ->method('restore')
            ->with(
                static::identicalTo($order),
                static::identicalTo($context->getContext()),
                [SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethod->getId()],
            )
            ->willReturn($restored);

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->once())
            ->method('convertToCart')
            ->willReturn(new Cart('converted-order-token'));

        $cartRuleLoader = static::createStub(CartRuleLoader::class);
        $cartRuleLoader
            ->method('loadByCart')
            ->willReturnCallback(static fn (SalesChannelContext $context, Cart $cart): RuleLoaderResult => new RuleLoaderResult($cart, new RuleCollection()));

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $orderService,
            $orderRepository,
            $orderConverter,
            $cartRuleLoader,
            $orderRestorer,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            $gatewayRoute,
            new ExtensionDispatcher(new EventDispatcher())
        );

        $request = self::getRequest(['paymentMethodId' => $paymentMethod->getId(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $context);
    }

    public function testLoadsTheOrderWithTheAssociationsTheRestorationRequires(): void
    {
        $orderRestorer = $this->createMock(OrderRestorer::class);
        $orderRestorer
            ->expects($this->once())
            ->method('addRequiredAssociations')
            ->willReturnCallback(static fn (Criteria $criteria): Criteria => $criteria->addAssociation('restorationMarker'));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static fn (OrderPaymentMethodChangedCriteriaEvent $event): bool => $event->getCriteria()->hasAssociation('restorationMarker')))
            ->willReturnArgument(0);

        $paymentOrderRoute = new SetPaymentOrderRoute(
            static::createStub(OrderService::class),
            new StaticEntityRepository([new OrderCollection()], new OrderDefinition()),
            static::createStub(OrderConverter::class),
            static::createStub(CartRuleLoader::class),
            $orderRestorer,
            $eventDispatcher,
            static::createStub(InitialStateIdLoader::class),
            static::createStub(AbstractCheckoutGatewayRoute::class),
            new ExtensionDispatcher(new EventDispatcher())
        );

        $orderId = Uuid::randomHex();
        $this->expectExceptionObject(OrderException::orderNotFound($orderId));

        $paymentOrderRoute->setPayment(
            self::getRequest(['paymentMethodId' => Uuid::randomHex(), 'orderId' => $orderId]),
            Generator::generateSalesChannelContext()
        );
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $response = new SetPaymentOrderRouteResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('set-payment-order-route.set-payment.pre', static function (SetPaymentOrderRouteExtension $extension) use ($request, $context, $response): void {
            static::assertSame(['request' => $request, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new SetPaymentOrderRoute(
            static::createStub(OrderService::class),
            static::createStub(EntityRepository::class),
            static::createStub(OrderConverter::class),
            static::createStub(CartRuleLoader::class),
            static::createStub(OrderRestorer::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            static::createStub(AbstractCheckoutGatewayRoute::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->setPayment($request, $context));
    }

    /**
     * @return iterable<string, Request[]>
     */
    public static function requestDataProvider(): iterable
    {
        yield 'request without payment method or order ids' => [
            self::getRequest([]),
        ];
        yield 'request with malformed payment method id' => [
            self::getRequest(['paymentMethodId' => 'some payment method id']),
        ];
        yield 'request with valid payment method id and malformed order id' => [
            self::getRequest(['paymentMethodId' => Uuid::randomHex(), 'orderId' => 'some order id']),
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
