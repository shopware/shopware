<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Account\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Order\OrderRestorer;
use Shopware\Core\Checkout\Cart\Order\RestoredOrder;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopware\Storefront\Event\RouteRequest\PaymentMethodRouteRequestEvent;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\MetaInformation;
use Shopware\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountEditOrderPageLoader::class)]
class AccountOrderEditPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private OrderRoute&MockObject $orderRoute;

    private AbstractTranslator&Stub $translator;

    private GenericPageLoader&Stub $genericPageLoader;

    private AbstractCheckoutGatewayRoute&Stub $checkoutGatewayRoute;

    private OrderRestorer&Stub $orderRestorer;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->orderRoute = $this->createMock(OrderRoute::class);
        $this->translator = static::createStub(AbstractTranslator::class);
        $this->genericPageLoader = static::createStub(GenericPageLoader::class);
        $this->checkoutGatewayRoute = static::createStub(AbstractCheckoutGatewayRoute::class);
        $this->orderRestorer = static::createStub(OrderRestorer::class);
        $this->orderRestorer
            ->method('addRequiredAssociations')
            ->willReturnArgument(0);
        $this->orderRestorer
            ->method('restore')
            ->willReturnCallback(static fn (OrderEntity $order): RestoredOrder => new RestoredOrder($order, Generator::generateSalesChannelContext(), new Cart('restored-token')));
    }

    public function testLoad(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $orders = new OrderCollection([$order]);

        $orderResponse = new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                1,
                $orders,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($orderResponse);

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');

        $genericPageLoader = $this->createMock(GenericPageLoader::class);
        $genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($page);

        $translator = $this->createMock(AbstractTranslator::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->willReturn('translated');

        $filteredPaymentMethod = new PaymentMethodEntity();
        $filteredPaymentMethod->setId(Uuid::randomHex());
        $filteredPaymentMethod->setAfterOrderEnabled(false);
        $remainingPaymentMethod = new PaymentMethodEntity();
        $remainingPaymentMethod->setId(Uuid::randomHex());
        $remainingPaymentMethod->setAfterOrderEnabled(true);
        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn(new CheckoutGatewayRouteResponse(
                new PaymentMethodCollection([$filteredPaymentMethod, $remainingPaymentMethod]),
                new ShippingMethodCollection(),
                new ErrorCollection(),
            ));

        $pageLoader = $this->createPageLoader(
            genericPageLoader: $genericPageLoader,
            translator: $translator,
            checkoutGatewayRoute: $checkoutGatewayRoute,
        );

        $request = new Request();
        $request->attributes->set('orderId', $order->getId());

        $page = $pageLoader->load($request, Generator::generateSalesChannelContext());

        static::assertSame($order, $page->getOrder());
        $metaInformation = $page->getMetaInformation();
        static::assertNotNull($metaInformation);
        static::assertSame('translated | testshop', $metaInformation->getMetaTitle());
        static::assertSame('noindex,follow', $metaInformation->getRobots());

        static::assertSame([$remainingPaymentMethod], array_values($page->getPaymentMethods()->getElements()));

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(3, $events);

        static::assertInstanceOf(OrderRouteRequestEvent::class, $events[0]);
        static::assertInstanceOf(PaymentMethodRouteRequestEvent::class, $events[1]);
        static::assertInstanceOf(AccountEditOrderPageLoadedEvent::class, $events[2]);
    }

    public function testLoadsThePaymentMethodsForTheRestoredOrder(): void
    {
        $order = $this->expectOrder();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $restored = new RestoredOrder($order, Generator::generateSalesChannelContext(), new Cart('restored-token'));

        $orderRestorer = $this->createMock(OrderRestorer::class);
        $orderRestorer
            ->method('addRequiredAssociations')
            ->willReturnArgument(0);
        $orderRestorer
            ->expects($this->once())
            ->method('restore')
            ->with(static::identicalTo($order), static::identicalTo($salesChannelContext->getContext()))
            ->willReturn($restored);

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static fn (Request $storeApiRequest): bool => $storeApiRequest->query->get('onlyAvailable') === '1'),
                static::identicalTo($restored->cart),
                static::identicalTo($restored->context),
            )
            ->willReturn(new CheckoutGatewayRouteResponse(
                new PaymentMethodCollection(),
                new ShippingMethodCollection(),
                new ErrorCollection(),
            ));

        $request = new Request();
        $request->attributes->set('orderId', $order->getId());

        $this->createPageLoader(checkoutGatewayRoute: $checkoutGatewayRoute, orderRestorer: $orderRestorer)
            ->load($request, $salesChannelContext);
    }

    public function testRestoresTheOrderAfterThePaymentMethodRouteRequestEventWasDispatched(): void
    {
        $order = $this->expectOrder();
        $this->expectPaymentMethods(new PaymentMethodCollection());

        $orderRestorer = $this->createMock(OrderRestorer::class);
        $orderRestorer
            ->method('addRequiredAssociations')
            ->willReturnArgument(0);
        $orderRestorer
            ->expects($this->once())
            ->method('restore')
            ->willReturnCallback(function (OrderEntity $order, Context $context): RestoredOrder {
                $events = $this->eventDispatcher->getEventsOfClass(PaymentMethodRouteRequestEvent::class);
                static::assertCount(1, $events);
                static::assertSame($events[0]->getContext(), $context);

                return new RestoredOrder($order, Generator::generateSalesChannelContext(), new Cart('restored-token'));
            });

        $request = new Request();
        $request->attributes->set('orderId', $order->getId());

        $this->createPageLoader(orderRestorer: $orderRestorer)->load($request, Generator::generateSalesChannelContext());
    }

    public function testLoadsTheOrderWithTheAssociationsTheRestorationRequires(): void
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        $orderRestorer = $this->createMock(OrderRestorer::class);
        $orderRestorer
            ->expects($this->once())
            ->method('addRequiredAssociations')
            ->willReturnCallback(function (Criteria $criteria): Criteria {
                static::assertSame([], $this->eventDispatcher->getEventsOfClass(OrderRouteRequestEvent::class));

                return $criteria->addAssociation('restorationMarker');
            });
        $orderRestorer
            ->method('restore')
            ->willReturn(new RestoredOrder($order, Generator::generateSalesChannelContext(), new Cart('restored-token')));

        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::anything(),
                static::anything(),
                static::callback(static fn (Criteria $criteria): bool => $criteria->hasAssociation('restorationMarker')),
            )
            ->willReturn(new OrderRouteResponse(
                new EntitySearchResult(
                    OrderDefinition::ENTITY_NAME,
                    1,
                    new OrderCollection([$order]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            ));

        $this->expectPaymentMethods(new PaymentMethodCollection());

        $request = new Request();
        $request->attributes->set('orderId', $order->getId());

        $this->createPageLoader(orderRestorer: $orderRestorer)->load($request, Generator::generateSalesChannelContext());
    }

    public function testLoadCancelled(): void
    {
        $order = new OrderEntity();
        $order->setStateId(OrderStates::STATE_CANCELLED);
        $smEntity = new StateMachineStateEntity();
        $smEntity->setTechnicalName(OrderStates::STATE_CANCELLED);
        $order->setStateMachineState($smEntity);
        $order->setId(Uuid::randomHex());

        $orders = new OrderCollection([$order]);

        $orderResponse = new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                1,
                $orders,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($orderResponse);

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');

        $genericPageLoader = $this->createMock(GenericPageLoader::class);
        $genericPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($page);

        static::expectException(OrderException::class);

        $request = new Request();
        $request->attributes->set('orderId', $order->getId());

        $pageLoader = $this->createPageLoader(genericPageLoader: $genericPageLoader);

        $page = $pageLoader->load($request, Generator::generateSalesChannelContext());
    }

    public function testLoadSelectsThePaymentMethodOfTheOrder(): void
    {
        $orderPaymentMethod = new PaymentMethodEntity();
        $orderPaymentMethod->setId(Uuid::randomHex());
        $orderPaymentMethod->setAfterOrderEnabled(true);

        $contextPaymentMethod = new PaymentMethodEntity();
        $contextPaymentMethod->setId(Uuid::randomHex());
        $contextPaymentMethod->setAfterOrderEnabled(true);

        $this->expectPaymentMethods(new PaymentMethodCollection([$orderPaymentMethod, $contextPaymentMethod]));

        $order = $this->expectOrder($orderPaymentMethod->getId());

        $request = new Request();
        $request->attributes->set('orderId', $order->getId());

        $page = $this->createPageLoader()->load(
            $request,
            Generator::generateSalesChannelContext(paymentMethod: $contextPaymentMethod)
        );

        static::assertSame($orderPaymentMethod->getId(), $page->getSelectedPaymentMethodId());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLoadSelectsThePaymentMethodOfTheLatestOrderTransaction(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());
        $paymentMethod->setAfterOrderEnabled(true);

        $this->expectPaymentMethods(new PaymentMethodCollection([$paymentMethod]));

        $previousTransaction = new OrderTransactionEntity();
        $previousTransaction->setId(Uuid::randomHex());
        $previousTransaction->setPaymentMethodId(Uuid::randomHex());

        $latestTransaction = new OrderTransactionEntity();
        $latestTransaction->setId(Uuid::randomHex());
        $latestTransaction->setPaymentMethodId($paymentMethod->getId());

        $order = $this->expectOrder();
        $order->setTransactions(new OrderTransactionCollection([$previousTransaction, $latestTransaction]));

        $request = new Request();
        $request->attributes->set('orderId', $order->getId());

        $page = $this->createPageLoader()->load($request, Generator::generateSalesChannelContext());

        static::assertSame($paymentMethod->getId(), $page->getSelectedPaymentMethodId());
    }

    public function testLoadSelectsTheRequestedPaymentMethod(): void
    {
        $requestedPaymentMethod = new PaymentMethodEntity();
        $requestedPaymentMethod->setId(Uuid::randomHex());
        $requestedPaymentMethod->setAfterOrderEnabled(true);

        $this->expectPaymentMethods(new PaymentMethodCollection([$requestedPaymentMethod]));

        $order = $this->expectOrder(Uuid::randomHex());

        $request = new Request();
        $request->attributes->set('orderId', $order->getId());
        $request->query->set('paymentMethodId', $requestedPaymentMethod->getId());

        $page = $this->createPageLoader()->load($request, Generator::generateSalesChannelContext());

        static::assertSame($requestedPaymentMethod->getId(), $page->getSelectedPaymentMethodId());
    }

    public function testLoadIgnoresARequestedPaymentMethodThatIsNotAvailable(): void
    {
        $orderPaymentMethod = new PaymentMethodEntity();
        $orderPaymentMethod->setId(Uuid::randomHex());
        $orderPaymentMethod->setAfterOrderEnabled(true);

        $this->expectPaymentMethods(new PaymentMethodCollection([$orderPaymentMethod]));

        $order = $this->expectOrder($orderPaymentMethod->getId());

        $request = new Request();
        $request->attributes->set('orderId', $order->getId());
        $request->query->set('paymentMethodId', Uuid::randomHex());

        $page = $this->createPageLoader()->load($request, Generator::generateSalesChannelContext());

        static::assertSame($orderPaymentMethod->getId(), $page->getSelectedPaymentMethodId());
    }

    private function expectOrder(?string $paymentMethodId = null): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        if ($paymentMethodId !== null) {
            $transaction = new OrderTransactionEntity();
            $transaction->setId(Uuid::randomHex());
            $transaction->setPaymentMethodId($paymentMethodId);

            $order->setPrimaryOrderTransaction($transaction);
        }

        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn(new OrderRouteResponse(
                new EntitySearchResult(
                    OrderDefinition::ENTITY_NAME,
                    1,
                    new OrderCollection([$order]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            ));

        return $order;
    }

    private function expectPaymentMethods(PaymentMethodCollection $paymentMethods): void
    {
        $this->checkoutGatewayRoute
            ->method('load')
            ->willReturn(new CheckoutGatewayRouteResponse(
                $paymentMethods,
                new ShippingMethodCollection(),
                new ErrorCollection(),
            ));
    }

    private function createPageLoader(
        ?GenericPageLoader $genericPageLoader = null,
        ?AbstractCheckoutGatewayRoute $checkoutGatewayRoute = null,
        ?OrderRestorer $orderRestorer = null,
        ?AbstractTranslator $translator = null,
    ): AccountEditOrderPageLoader {
        return new AccountEditOrderPageLoader(
            $genericPageLoader ?? $this->genericPageLoader,
            $this->eventDispatcher,
            $this->orderRoute,
            $checkoutGatewayRoute ?? $this->checkoutGatewayRoute,
            $orderRestorer ?? $this->orderRestorer,
            static::createStub(OrderService::class),
            $translator ?? $this->translator,
        );
    }
}
