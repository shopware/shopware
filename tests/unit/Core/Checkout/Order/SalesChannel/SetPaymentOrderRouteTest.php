<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
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
    public function testSetPaymentProvidesOrderCartToPaymentAvailability(): void
    {
        $orderId = Uuid::randomHex();
        $paymentMethodId = Uuid::randomHex();

        $order = new OrderEntity();
        $order->setId($orderId);

        /** @var StaticEntityRepository<OrderCollection> $staticRepository */
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

        $paymentOrderRoute = new SetPaymentOrderRoute(
            static::createStub(OrderService::class),
            $staticRepository,
            static::createStub(OrderConverter::class),
            static::createStub(CartRuleLoader::class),
            static::createStub(CartService::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
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

    public function testPaymentMethodNotAfterOrderEnabled(): void
    {
        $this->expectExceptionObject(OrderException::paymentMethodNotChangeable());

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        /** @var StaticEntityRepository<OrderCollection> $staticRepository */
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

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $orderService,
            $staticRepository,
            static::createStub(OrderConverter::class),
            static::createStub(CartRuleLoader::class),
            static::createStub(CartService::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
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
            static::createStub(CartRuleLoader::class),
            static::createStub(CartService::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(InitialStateIdLoader::class),
            $gatewayRoute
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

        $gatewayRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::identicalTo($order),
                static::identicalTo($salesChannelContext->getContext()),
                [SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId]
            )
            ->willReturn($orderContext);

        $convertedCart = new Cart('converted-order-token');
        $orderConverter
            ->expects($this->once())
            ->method('convertToCart')
            ->with(static::identicalTo($order), static::identicalTo($orderContext->getContext()))
            ->willReturn($convertedCart);

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('setCart')
            ->with(static::callback(static function (Cart $cart) use ($orderContext): bool {
                static::assertSame($orderContext->getToken(), $cart->getToken());

                return true;
            }));

        $paymentRoute = $this->createMock(AbstractPaymentMethodRoute::class);
        $paymentRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static function (Request $request) use ($orderId): bool {
                    static::assertSame('1', $request->query->get('onlyAvailable'));
                    static::assertSame($orderId, $request->attributes->get('orderId'));

                    return true;
                }),
                static::identicalTo($orderContext),
                static::isInstanceOf(Criteria::class)
            )
            ->willReturn($this->createPaymentMethodRouteResponse(new PaymentMethodCollection()));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->method('dispatch')
            ->willReturnArgument(0);

        $route = new SetPaymentOrderRoute(
            $this->createMock(OrderService::class),
            new StaticEntityRepository([new OrderCollection([$order])]),
            $paymentRoute,
            $orderConverter,
            $this->createMock(CartRuleLoader::class),
            $cartService,
            $eventDispatcher,
            $this->createMock(InitialStateIdLoader::class)
        );

        $this->expectException(OrderException::class);

        $route->setPayment(new Request(request: [
            'orderId' => $orderId,
            'paymentMethodId' => $paymentMethodId,
        ]), $salesChannelContext);
    }

    private function createPaymentMethodRouteResponse(PaymentMethodCollection $paymentMethods): PaymentMethodRouteResponse
    {
        return new PaymentMethodRouteResponse(
            new EntitySearchResult(
                PaymentMethodDefinition::ENTITY_NAME,
                $paymentMethods->count(),
                $paymentMethods,
                null,
                new Criteria(),
                Generator::generateSalesChannelContext()->getContext()
            )
        );
    }
}
