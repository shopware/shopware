<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
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
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\AccountOrderController;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderDetailPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(AccountOrderController::class)]
class AccountOrderControllerTest extends TestCase
{
    private AccountOrderControllerTestClass $controller;

    private MockObject&AbstractOrderRoute $orderRouteMock;

    private MockObject&AccountEditOrderPageLoader $accountEditOrderPageLoaderMock;

    private MockObject&AbstractHandlePaymentMethodRoute $handlePaymentRouteMock;

    private MockObject&OrderService $orderServiceMock;

    protected function setUp(): void
    {
        $this->orderRouteMock = $this->createMock(AbstractOrderRoute::class);
        $this->accountEditOrderPageLoaderMock = $this->createMock(AccountEditOrderPageLoader::class);
        $this->handlePaymentRouteMock = $this->createMock(AbstractHandlePaymentMethodRoute::class);
        $this->orderServiceMock = $this->createPartialMock(OrderService::class, ['__construct']);

        $this->controller = new AccountOrderControllerTestClass(
            $this->createMock(AccountOrderPageLoader::class),
            $this->accountEditOrderPageLoaderMock,
            $this->createMock(AbstractContextSwitchRoute::class),
            $this->createMock(AbstractCancelOrderRoute::class),
            $this->createMock(AbstractSetPaymentOrderRoute::class),
            $this->handlePaymentRouteMock,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(AccountOrderDetailPageLoader::class),
            $this->orderRouteMock,
            $this->createMock(SalesChannelContextServiceInterface::class),
            $this->createMock(SystemConfigService::class),
            $this->orderServiceMock,
        );
    }

    public function testEditOrderNotFound(): void
    {
        $ids = new IdsCollection();

        // Ensure it redirects to the correct route
        $router = static::createMock(RouterInterface::class);
        $router
            ->expects(static::once())
            ->method('generate')
            ->with('frontend.account.order.page')
            ->willReturn('http://localhost/account/order');

        $container = new ContainerBuilder();
        $container->set('event_dispatcher', static::createMock(EventDispatcherInterface::class));
        $container->set('router', $router);

        $this->controller->setContainer($container);

        $response = $this->controller->editOrder($ids->get('order'), new Request(), Generator::createSalesChannelContext());

        // Ensure flash massage is shown
        static::assertEquals('danger error.CHECKOUT__ORDER_ORDER_NOT_FOUND', $this->controller->flash);
        static::assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertEquals('http://localhost/account/order', $response->headers->get('Location'));
    }

    public function testEditOrderInvalidUuid(): void
    {
        // Ensure it redirects to the correct route
        $router = static::createMock(RouterInterface::class);
        $router
            ->expects(static::once())
            ->method('generate')
            ->with('frontend.account.order.page')
            ->willReturn('http://localhost/account/order');

        $container = new ContainerBuilder();
        $container->set('event_dispatcher', static::createMock(EventDispatcherInterface::class));
        $container->set('router', $router);

        $this->controller->setContainer($container);

        // Ensure invalid uuid exception is thrown
        $this->orderRouteMock->method('load')->willThrowException(new InvalidUuidException('invalid-id'));

        $response = $this->controller->editOrder('invalid-id', new Request(), Generator::createSalesChannelContext());

        // Ensure flash massage is shown
        static::assertEquals('danger error.CHECKOUT__ORDER_ORDER_NOT_FOUND', $this->controller->flash);
        static::assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertEquals('http://localhost/account/order', $response->headers->get('Location'));
    }

    public function testOrderAlreadyPaid(): void
    {
        $ids = new IdsCollection();

        $salesChannelContext = Generator::createSalesChannelContext();
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

        // Ensure it redirects to the correct route
        $router = static::createMock(RouterInterface::class);
        $router
            ->expects(static::once())
            ->method('generate')
            ->with('frontend.account.order.page')
            ->willReturn('http://localhost/account/order');

        $dispatcher = static::createMock(EventDispatcherInterface::class);

        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('event_dispatcher', $dispatcher);

        $this->controller->setContainer($container);

        $this->orderRouteMock->method('load')->willReturn($accountRouteResponse);
        $this->accountEditOrderPageLoaderMock->method('load')->willThrowException(OrderException::orderAlreadyPaid($ids->get('order')));

        $response = $this->controller->editOrder($ids->get('order'), new Request(), $salesChannelContext);

        // Ensure flash massage is shown
        static::assertEquals('danger error.CHECKOUT__ORDER_ORDER_ALREADY_PAID', $this->controller->flash);
        static::assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertEquals('http://localhost/account/order', $response->headers->get('Location'));
    }

    public function testCancelOrderRedirectsToCorrectRouteForLoggedInCustomer(): void
    {
        $routerMock = $this->createMock(RouterInterface::class);
        $salesChannelContextMock = $this->createMock(SalesChannelContext::class);
        $dispatcher = static::createMock(EventDispatcherInterface::class);

        $container = new ContainerBuilder();
        $container->set('router', $routerMock);
        $container->set('event_dispatcher', $dispatcher);
        $this->controller->setContainer($container);

        $customer = new CustomerEntity();
        $customer->setGuest(false);
        $salesChannelContextMock->method('getCustomer')->willReturn($customer);

        $expectedRouteName = 'frontend.account.order.page';
        $expectedRedirectUrl = 'http://localhost/account/order';
        $routerMock->expects(static::once())
            ->method('generate')
            ->with(static::equalTo($expectedRouteName))
            ->willReturn($expectedRedirectUrl);

        $request = new Request();
        $request->attributes->set('orderId', Uuid::randomHex());

        $response = $this->controller->cancelOrder($request, $salesChannelContextMock);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertEquals($expectedRedirectUrl, $response->getTargetUrl());
    }

    public function testCancelOrderRedirectsToCorrectRouteForGuestCustomer(): void
    {
        $routerMock = $this->createMock(RouterInterface::class);
        $salesChannelContextMock = $this->createMock(SalesChannelContext::class);
        $dispatcher = static::createMock(EventDispatcherInterface::class);

        $container = new ContainerBuilder();
        $container->set('router', $routerMock);
        $container->set('event_dispatcher', $dispatcher);

        $this->controller->setContainer($container);

        $customer = new CustomerEntity();
        $customer->setGuest(true);
        $salesChannelContextMock->method('getCustomer')->willReturn($customer);

        $expectedRouteName = 'frontend.account.order.single.page';
        $expectedRedirectUrl = 'http://localhost/account/order/guest';
        $routerMock->expects(static::once())
            ->method('generate')
            ->with(static::equalTo($expectedRouteName))
            ->willReturn($expectedRedirectUrl);

        $request = new Request();
        $request->attributes->set('orderId', Uuid::randomHex());
        $request->attributes->set('deepLinkCode', 'deep-link-code');

        $response = $this->controller->cancelOrder($request, $salesChannelContextMock);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertEquals($expectedRedirectUrl, $response->getTargetUrl());
    }

    public function testTransactionsStateMachineAssociationIsLoadedOnOrderUpdate(): void
    {
        $container = new ContainerBuilder();
        $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));
        $container->set('router', $this->createMock(RouterInterface::class));

        $this->controller->setContainer($container);

        $ids = new IdsCollection();

        $salesChannelContext = Generator::createSalesChannelContext();
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

        $this->orderRouteMock
            ->expects(static::once())
            ->method('load')
            ->with($request = new Request(), $salesChannelContext, $criteria)
            ->willReturn($accountRouteResponse);

        $this->handlePaymentRouteMock
            ->expects(static::once())
            ->method('load')
            ->with(static::isInstanceOf(Request::class), $salesChannelContext)
            ->willReturn(new HandlePaymentMethodRouteResponse(new RedirectResponse('http://doesnotexist.com')));

        $this->controller->updateOrder($ids->get('order'), $request, $salesChannelContext);
    }
}

/**
 * @internal
 */
class AccountOrderControllerTestClass extends AccountOrderController
{
    public string $renderStorefrontView;

    public string $flash;

    /**
     * @var array<array-key, mixed>
     */
    public array $renderStorefrontParameters;

    /**
     * @param array<array-key, mixed> $parameters
     */
    protected function renderStorefront(string $view, array $parameters = []): Response
    {
        $this->renderStorefrontView = $view;
        $this->renderStorefrontParameters = $parameters;

        return new Response();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function trans(string $snippet, array $parameters = []): string
    {
        return $snippet;
    }

    protected function hook(Hook $hook): void
    {
        // nothing
    }

    protected function addFlash(string $type, mixed $message): void
    {
        $this->flash = $type . ' ' . $message;
    }
}
