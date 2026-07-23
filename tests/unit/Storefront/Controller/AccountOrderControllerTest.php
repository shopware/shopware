<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractCancelOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractSetPaymentOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractHandlePaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Controller\AccountOrderController;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderDetailPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoaderInterface;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountOrderController::class)]
class AccountOrderControllerTest extends TestCase
{
    private AccountOrderControllerTestClass $controller;

    private Stub&AbstractOrderRoute $orderRouteMock;

    private Stub&AccountEditOrderPageLoader $accountEditOrderPageLoaderMock;

    private Stub&AbstractHandlePaymentMethodRoute $handlePaymentRouteMock;

    private Stub&OrderService $orderServiceMock;

    protected function setUp(): void
    {
        $this->orderRouteMock = static::createStub(AbstractOrderRoute::class);
        $this->accountEditOrderPageLoaderMock = static::createStub(AccountEditOrderPageLoader::class);
        $this->handlePaymentRouteMock = static::createStub(AbstractHandlePaymentMethodRoute::class);

        $this->orderServiceMock = static::createStub(OrderService::class);

        $this->controller = $this->createController(
            $this->orderRouteMock,
            $this->handlePaymentRouteMock,
        );
    }

    public function testEditOrderNotFound(): void
    {
        $ids = new IdsCollection();

        $response = $this->controller->editOrder($ids->get('order'), new Request(), Generator::generateSalesChannelContext());

        // Ensure flash massage is shown
        static::assertSame(['danger' => ['error.CHECKOUT__ORDER_ORDER_NOT_FOUND']], $this->controller->flashBag);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.order.page', $response->getTargetUrl());
    }

    public function testEditOrderInvalidUuid(): void
    {
        // Ensure invalid uuid exception is thrown
        $this->orderRouteMock->method('load')->willThrowException(new InvalidUuidException('invalid-id'));

        $response = $this->controller->editOrder('invalid-id', new Request(), Generator::generateSalesChannelContext());

        // Ensure flash massage is shown
        static::assertSame(['danger' => ['error.CHECKOUT__ORDER_ORDER_NOT_FOUND']], $this->controller->flashBag);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.order.page', $response->getTargetUrl());
    }

    public function testOrderAlreadyPaid(): void
    {
        $ids = new IdsCollection();

        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->assign([
            'currency' => (new CurrencyEntity())->assign([
                'id' => $ids->get('currency'),
            ]),
        ]);

        $order = (new OrderEntity())->assign([
            '_uniqueIdentifier' => Uuid::randomHex(),
            'currencyId' => $ids->get('currency'),
            'deliveries' => new OrderDeliveryCollection(),
        ]);
        $orders = new OrderCollection([$order]);

        $accountRouteResponse = new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                1,
                $orders,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $dispatcher = static::createStub(EventDispatcherInterface::class);

        $container = new ContainerBuilder();
        $container->set('event_dispatcher', $dispatcher);

        $this->controller->setContainer($container);

        $this->orderRouteMock->method('load')->willReturn($accountRouteResponse);
        $this->accountEditOrderPageLoaderMock->method('load')->willThrowException(OrderException::orderAlreadyPaid($ids->get('order')));

        $response = $this->controller->editOrder($ids->get('order'), new Request(), $salesChannelContext);

        // Ensure flash massage is shown
        static::assertSame(['danger' => ['error.CHECKOUT__ORDER_ORDER_ALREADY_PAID']], $this->controller->flashBag);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.order.page', $response->getTargetUrl());
    }

    public function testCancelOrderRedirectsToCorrectRouteForLoggedInCustomer(): void
    {
        $salesChannelContextMock = static::createStub(SalesChannelContext::class);

        $customer = new CustomerEntity();
        $customer->setGuest(false);
        $salesChannelContextMock->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set('orderId', Uuid::randomHex());

        $response = $this->controller->cancelOrder($request, $salesChannelContextMock);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('frontend.account.order.page', $response->getTargetUrl());
    }

    public function testCancelOrderRedirectsToCorrectRouteForGuestCustomer(): void
    {
        $salesChannelContextMock = static::createStub(SalesChannelContext::class);

        $customer = new CustomerEntity();
        $customer->setGuest(true);
        $salesChannelContextMock->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set('orderId', Uuid::randomHex());
        $request->attributes->set('deepLinkCode', 'deep-link-code');

        $response = $this->controller->cancelOrder($request, $salesChannelContextMock);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('frontend.account.order.single.page', $response->getTargetUrl());
    }

    public function testTransactionsStateMachineAssociationIsLoadedOnOrderUpdate(): void
    {
        $ids = new IdsCollection();

        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->assign([
            'currency' => (new CurrencyEntity())->assign([
                'id' => $ids->get('currency'),
            ]),
        ]);

        $criteria = new Criteria([$ids->get('order')]);
        $criteria->addAssociation('transactions.stateMachineState');

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setTechnicalName(OrderTransactionStates::STATE_CANCELLED);

        $transaction = new OrderTransactionEntity();
        $transaction->setId($ids->get('transaction'));
        $transaction->setStateMachineState($stateMachineState);

        // Mock the OrderEntity with transactions
        $order = new OrderEntity();
        $order->setId($ids->get('order'));
        $order->setCurrencyId($ids->get('currency'));
        $order->setDeliveries(new OrderDeliveryCollection());
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        $orders = new OrderCollection([$order]);

        $accountRouteResponse = new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                1,
                $orders,
                null,
                $criteria,
                $salesChannelContext->getContext()
            )
        );

        $orderRoute = $this->createMock(AbstractOrderRoute::class);
        $orderRoute
            ->expects($this->once())
            ->method('load')
            ->with($request = new Request(), $salesChannelContext, $criteria)
            ->willReturn($accountRouteResponse);

        $this->orderServiceMock
            ->method('isPaymentChangeableByTransactionState')
            ->willReturn(true);

        $handlePaymentRoute = $this->createMock(AbstractHandlePaymentMethodRoute::class);
        $handlePaymentRoute
            ->expects($this->once())
            ->method('load')
            ->with(static::isInstanceOf(Request::class), $salesChannelContext)
            ->willReturn(new HandlePaymentMethodRouteResponse(new RedirectResponse('https://doesnotexist.com')));

        $controller = $this->createController($orderRoute, $handlePaymentRoute);

        $controller->updateOrder($ids->get('order'), $request, $salesChannelContext);
    }

    private function createController(
        AbstractOrderRoute $orderRoute,
        AbstractHandlePaymentMethodRoute $handlePaymentRoute,
    ): AccountOrderControllerTestClass {
        return new AccountOrderControllerTestClass(
            static::createStub(AccountOrderPageLoader::class),
            $this->accountEditOrderPageLoaderMock,
            static::createStub(AbstractContextSwitchRoute::class),
            static::createStub(AbstractCancelOrderRoute::class),
            static::createStub(AbstractSetPaymentOrderRoute::class),
            $handlePaymentRoute,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(AccountOrderDetailPageLoader::class),
            $orderRoute,
            static::createStub(SalesChannelContextServiceInterface::class),
            static::createStub(SystemConfigService::class),
            $this->orderServiceMock,
            static::createStub(HeaderPageletLoaderInterface::class),
            static::createStub(FooterPageletLoaderInterface::class),
        );
    }
}

/**
 * @internal
 */
class AccountOrderControllerTestClass extends AccountOrderController
{
    use StorefrontControllerMockTrait;
}
