<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Account\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
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
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
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
#[CoversClass(AccountEditOrderPageLoader::class)]
class AccountOrderEditPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private OrderRoute&MockObject $orderRoute;

    private AccountEditOrderPageLoader $pageLoader;

    private AbstractTranslator&MockObject $translator;

    private GenericPageLoader&MockObject $genericPageLoader;

    private AbstractCheckoutGatewayRoute&MockObject $checkoutGatewayRoute;

    private OrderConverter&MockObject $orderConverter;

    private CartService&MockObject $cartService;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->orderRoute = $this->createMock(OrderRoute::class);
        $this->translator = $this->createMock(AbstractTranslator::class);
        $this->genericPageLoader = $this->createMock(GenericPageLoader::class);
        $this->checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $this->orderConverter = $this->createMock(OrderConverter::class);
        $this->cartService = $this->createMock(CartService::class);

        $this->pageLoader = new AccountEditOrderPageLoader(
            $this->genericPageLoader,
            $this->eventDispatcher,
            $this->orderRoute,
            $this->checkoutGatewayRoute,
            $this->orderConverter,
            $this->createMock(OrderService::class),
            $this->translator,
            $this->cartService,
        );
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
            ->expects(static::once())
            ->method('load')
            ->willReturn($orderResponse);

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');

        $this->genericPageLoader
            ->expects(static::once())
            ->method('load')
            ->willReturn($page);

        $this->translator
            ->expects(static::once())
            ->method('trans')
            ->willReturn('translated');

        $filteredPaymentMethod = new PaymentMethodEntity();
        $filteredPaymentMethod->setId(Uuid::randomHex());
        $filteredPaymentMethod->setAfterOrderEnabled(false);
        $remainingPaymentMethod = new PaymentMethodEntity();
        $remainingPaymentMethod->setId(Uuid::randomHex());
        $remainingPaymentMethod->setAfterOrderEnabled(true);
        $this->checkoutGatewayRoute
            ->expects(static::once())
            ->method('load')
            ->willReturn(new CheckoutGatewayRouteResponse(
                new PaymentMethodCollection([$filteredPaymentMethod, $remainingPaymentMethod]),
                new ShippingMethodCollection(),
                new ErrorCollection(),
            ));

        $page = $this->pageLoader->load(new Request(), Generator::createSalesChannelContext());

        static::assertEquals($order, $page->getOrder());
        static::assertEquals('translated | testshop', $page->getMetaInformation()?->getMetaTitle());
        static::assertEquals('noindex,follow', $page->getMetaInformation()?->getRobots());

        static::assertSame([$remainingPaymentMethod], array_values($page->getPaymentMethods()->getElements()));

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(3, $events);

        static::assertInstanceOf(OrderRouteRequestEvent::class, $events[0]);
        static::assertInstanceOf(PaymentMethodRouteRequestEvent::class, $events[1]);
        static::assertInstanceOf(AccountEditOrderPageLoadedEvent::class, $events[2]);
    }

    public function testCartInCartService(): void
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

        $this->checkoutGatewayRoute
            ->expects(static::once())
            ->method('load')
            ->willReturn(new CheckoutGatewayRouteResponse(
                new PaymentMethodCollection(),
                new ShippingMethodCollection(),
                new ErrorCollection(),
            ));

        $this->orderRoute
            ->expects(static::once())
            ->method('load')
            ->willReturn($orderResponse);

        $orderContext = Generator::createSalesChannelContext();

        $this->cartService
            ->expects(static::once())
            ->method('setCart')
            ->with(static::callback(function (Cart $cart) use ($orderContext) {
                return $cart->getToken() === $orderContext->getToken();
            }));

        $cart = new Cart('some-token');
        $this->orderConverter
            ->expects(static::once())
            ->method('convertToCart')
            ->willReturn($cart);

        $this->orderConverter
            ->expects(static::once())
            ->method('assembleSalesChannelContext')
            ->willReturn($orderContext);

        $request = new Request();
        $this->checkoutGatewayRoute
            ->expects(static::once())
            ->method('load')
            ->with($request, $cart, $orderContext)
            ->willReturn(new CheckoutGatewayRouteResponse(
                new PaymentMethodCollection(),
                new ShippingMethodCollection(),
                new ErrorCollection(),
            ));

        $this->pageLoader->load($request, Generator::createSalesChannelContext());
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
            ->expects(static::once())
            ->method('load')
            ->willReturn($orderResponse);

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $page->getMetaInformation()?->setMetaTitle('testshop');

        $this->genericPageLoader
            ->expects(static::once())
            ->method('load')
            ->willReturn($page);

        static::expectException(OrderException::class);
        $page = $this->pageLoader->load(new Request(), Generator::createSalesChannelContext());
    }
}
