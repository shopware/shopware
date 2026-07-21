<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Account\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
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
use Shopware\Core\Framework\Log\Package;
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
#[Package('checkout')]
#[CoversClass(AccountEditOrderPageLoader::class)]
class AccountOrderEditPageLoaderTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private OrderRoute&MockObject $orderRoute;

    private AbstractTranslator&Stub $translator;

    private GenericPageLoader&Stub $genericPageLoader;

    private AbstractCheckoutGatewayRoute&Stub $checkoutGatewayRoute;

    private OrderConverter&Stub $orderConverter;

    private CartService&Stub $cartService;

    protected function setUp(): void
    {
        $this->eventDispatcher = new CollectingEventDispatcher();
        $this->orderRoute = $this->createMock(OrderRoute::class);
        $this->translator = static::createStub(AbstractTranslator::class);
        $this->genericPageLoader = static::createStub(GenericPageLoader::class);
        $this->checkoutGatewayRoute = static::createStub(AbstractCheckoutGatewayRoute::class);
        $this->orderConverter = static::createStub(OrderConverter::class);
        $this->cartService = static::createStub(CartService::class);
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

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn(new CheckoutGatewayRouteResponse(
                new PaymentMethodCollection(),
                new ShippingMethodCollection(),
                new ErrorCollection(),
            ));

        $this->orderRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($orderResponse);

        $orderContext = Generator::generateSalesChannelContext();

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('setCart')
            ->with(static::callback(static function (Cart $cart) use ($orderContext) {
                return $cart->getToken() === $orderContext->getToken();
            }));

        $cart = new Cart('some-token');
        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->once())
            ->method('convertToCart')
            ->willReturn($cart);

        $orderConverter
            ->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->willReturn($orderContext);

        $request = new Request();
        $request->attributes->set('orderId', $order->getId());
        $request->query->set('onlyAvailable', 1);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->with($request, $cart, $orderContext)
            ->willReturn(new CheckoutGatewayRouteResponse(
                new PaymentMethodCollection(),
                new ShippingMethodCollection(),
                new ErrorCollection(),
            ));

        $pageLoader = $this->createPageLoader(
            checkoutGatewayRoute: $checkoutGatewayRoute,
            orderConverter: $orderConverter,
            cartService: $cartService,
        );

        $pageLoader->load($request, Generator::generateSalesChannelContext());
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

    private function createPageLoader(
        ?GenericPageLoader $genericPageLoader = null,
        ?AbstractCheckoutGatewayRoute $checkoutGatewayRoute = null,
        ?OrderConverter $orderConverter = null,
        ?AbstractTranslator $translator = null,
        ?CartService $cartService = null,
    ): AccountEditOrderPageLoader {
        return new AccountEditOrderPageLoader(
            $genericPageLoader ?? $this->genericPageLoader,
            $this->eventDispatcher,
            $this->orderRoute,
            $checkoutGatewayRoute ?? $this->checkoutGatewayRoute,
            $orderConverter ?? $this->orderConverter,
            static::createStub(OrderService::class),
            $translator ?? $this->translator,
            $cartService ?? $this->cartService,
        );
    }
}
