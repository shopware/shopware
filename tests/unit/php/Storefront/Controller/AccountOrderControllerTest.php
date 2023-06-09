<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
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
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestSessionStorage;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\AccountOrderController;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderDetailPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Controller\AccountOrderController
 */
class AccountOrderControllerTest extends TestCase
{
    private AccountOrderControllerTestClass $controller;

    private MockObject&AbstractOrderRoute $orderRouteMock;

    private MockObject&AccountEditOrderPageLoader $accountEditOrderPageLoaderMock;

    protected function setUp(): void
    {
        $this->orderRouteMock = $this->createMock(AbstractOrderRoute::class);
        $this->accountEditOrderPageLoaderMock = $this->createMock(AccountEditOrderPageLoader::class);

        $this->controller = new AccountOrderControllerTestClass(
            $this->createMock(AccountOrderPageLoader::class),
            $this->accountEditOrderPageLoaderMock,
            $this->createMock(AbstractContextSwitchRoute::class),
            $this->createMock(AbstractCancelOrderRoute::class),
            $this->createMock(AbstractSetPaymentOrderRoute::class),
            $this->createMock(AbstractHandlePaymentMethodRoute::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(AccountOrderDetailPageLoader::class),
            $this->orderRouteMock,
            $this->createMock(SalesChannelContextServiceInterface::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(OrderService::class)
        );
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

        $request = new Request();
        $session = new Session(new TestSessionStorage());
        $request->setSession($session);

        $stack = new RequestStack();
        $stack->push($request);

        // Ensure correct translation is used
        $translator = static::createMock(TranslatorInterface::class);
        $translator
            ->expects(static::once())
            ->method('trans')
            ->with('error.CHECKOUT__ORDER_ORDER_ALREADY_PAID', ['%orderNumber%' => null]);

        // Ensure it redirects to the correct route
        $router = static::createMock(RouterInterface::class);
        $router
            ->expects(static::once())
            ->method('generate')
            ->with('frontend.account.order.page')
            ->willReturn('http://localhost/account/order');

        $dispatcher = static::createMock(EventDispatcherInterface::class);

        $container = new ContainerBuilder();
        $container->set('request_stack', $stack);
        $container->set('translator', $translator);
        $container->set('router', $router);
        $container->set('event_dispatcher', $dispatcher);

        /** @var Request $updatedRequest */
        $updatedRequest = $stack->getMainRequest();

        $this->controller->setContainer($container);

        $this->orderRouteMock->method('load')->willReturn($accountRouteResponse);
        $this->accountEditOrderPageLoaderMock->method('load')->willThrowException(OrderException::orderAlreadyPaid($ids->get('order')));

        $this->controller->editOrder($ids->get('order'), $updatedRequest, $salesChannelContext);

        /** @var FlashBagInterface $flashBag */
        $flashBag = $updatedRequest->getSession()->getBag('flashes');
        $flashes = $flashBag->all();

        // Ensure flash massage is shown
        static::assertCount(1, $flashes);
        static::assertArrayHasKey('danger', $flashes);
        static::assertCount(1, $flashes['danger']);
    }
}

/**
 * @internal
 */
class AccountOrderControllerTestClass extends AccountOrderController
{
    public string $renderStorefrontView;

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

    protected function hook(Hook $hook): void
    {
        // nothing
    }
}
