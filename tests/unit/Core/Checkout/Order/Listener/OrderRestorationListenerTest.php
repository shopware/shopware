<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Listener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderRestorer;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Listener\OrderRestorationListener;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderRestorationListener::class)]
class OrderRestorationListenerTest extends TestCase
{
    private SalesChannelContext $sessionContext;

    private SalesChannelContext $restoredContext;

    private OrderRestorer $orderRestorer;

    protected function setUp(): void
    {
        $this->sessionContext = Generator::generateSalesChannelContext();
        $this->restoredContext = Generator::generateSalesChannelContext(token: 'restored-token');

        $orderConverter = static::createStub(OrderConverter::class);
        $orderConverter->method('assembleSalesChannelContext')->willReturn($this->restoredContext);
        $orderConverter->method('convertToCart')->willReturn(new Cart('converted-token'));

        $this->orderRestorer = new OrderRestorer($orderConverter, static::createStub(CartService::class));
    }

    public function testRunsRightAfterTheSessionContextIsResolved(): void
    {
        static::assertSame(
            [KernelEvents::CONTROLLER => [['restoreOrderState', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_POST]]],
            OrderRestorationListener::getSubscribedEvents(),
        );
    }

    #[DataProvider('requestWithoutOrderRestorationProvider')]
    public function testLeavesRequestsWithoutOrderRestorationAlone(Request $request): void
    {
        $orderRoute = $this->createMock(AbstractOrderRoute::class);
        $orderRoute->expects($this->never())->method('load');

        $attributes = $request->attributes->all();

        $this->createListener($orderRoute)->restoreOrderState($this->createEvent($request));

        static::assertSame($attributes, $request->attributes->all());
    }

    public static function requestWithoutOrderRestorationProvider(): \Generator
    {
        $orderId = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();

        yield 'route that did not opt in' => [new Request(
            query: ['orderId' => $orderId],
            attributes: [PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $context],
        )];

        yield 'opt-in that is not strictly true' => [new Request(
            query: ['orderId' => $orderId],
            attributes: [
                PlatformRequest::ATTRIBUTE_ALLOW_ORDER_RESTORATION => 'true',
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $context,
            ],
        )];

        yield 'opted-in route called without order id' => [new Request(
            attributes: [
                PlatformRequest::ATTRIBUTE_ALLOW_ORDER_RESTORATION => true,
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $context,
            ],
        )];

        yield 'order id sent as null' => [new Request(
            request: ['orderId' => null],
            attributes: [
                PlatformRequest::ATTRIBUTE_ALLOW_ORDER_RESTORATION => true,
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $context,
            ],
        )];

        yield 'request outside of a sales channel' => [new Request(
            query: ['orderId' => $orderId],
            attributes: [
                PlatformRequest::ATTRIBUTE_ALLOW_ORDER_RESTORATION => true,
                PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => Context::createDefaultContext(),
            ],
        )];
    }

    #[DataProvider('orderIdSourceProvider')]
    public function testReadsTheOrderIdFromPathQueryAndBodyInThatOrder(Request $request, string $expectedOrderId): void
    {
        $request->attributes->add([
            PlatformRequest::ATTRIBUTE_ALLOW_ORDER_RESTORATION => true,
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $this->sessionContext,
        ]);

        $orderRoute = $this->createMock(AbstractOrderRoute::class);
        $orderRoute->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (Request $routeRequest, SalesChannelContext $context, Criteria $criteria) use ($expectedOrderId): OrderRouteResponse {
                static::assertSame([$expectedOrderId], $criteria->getIds());

                return self::createOrderRouteResponse(self::createOrder($expectedOrderId));
            });

        $this->createListener($orderRoute)->restoreOrderState($this->createEvent($request));

        static::assertSame($expectedOrderId, $request->attributes->get('orderId'));
    }

    public static function orderIdSourceProvider(): \Generator
    {
        $pathOrderId = Uuid::randomHex();
        $queryOrderId = Uuid::randomHex();
        $bodyOrderId = Uuid::randomHex();

        yield 'path wins over query and body' => [
            new Request(query: ['orderId' => $queryOrderId], request: ['orderId' => $bodyOrderId], attributes: ['orderId' => $pathOrderId]),
            $pathOrderId,
        ];

        yield 'query wins over body' => [
            new Request(query: ['orderId' => $queryOrderId], request: ['orderId' => $bodyOrderId]),
            $queryOrderId,
        ];

        yield 'body is read last' => [
            new Request(request: ['orderId' => $bodyOrderId]),
            $bodyOrderId,
        ];
    }

    public function testRequiresALoggedInCustomer(): void
    {
        $orderRoute = $this->createMock(AbstractOrderRoute::class);
        $orderRoute->expects($this->never())->method('load');

        $request = $this->createOptedInRequest(Uuid::randomHex(), Generator::generateSalesChannelContext(overrides: ['customer' => null]));

        $this->expectExceptionObject(OrderException::customerNotLoggedIn());

        $this->createListener($orderRoute)->restoreOrderState($this->createEvent($request));
    }

    #[DataProvider('invalidOrderIdProvider')]
    public function testRejectsAnOrderIdThatIsNotAUuid(Request $request, string $reportedOrderId): void
    {
        $request->attributes->add([
            PlatformRequest::ATTRIBUTE_ALLOW_ORDER_RESTORATION => true,
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $this->sessionContext,
        ]);

        $orderRoute = $this->createMock(AbstractOrderRoute::class);
        $orderRoute->expects($this->never())->method('load');

        $this->expectExceptionObject(OrderException::invalidUuid($reportedOrderId));

        $this->createListener($orderRoute)->restoreOrderState($this->createEvent($request));
    }

    public static function invalidOrderIdProvider(): \Generator
    {
        yield 'not a uuid' => [new Request(query: ['orderId' => 'not-a-uuid']), 'not-a-uuid'];

        yield 'empty string' => [new Request(query: ['orderId' => '']), ''];

        yield 'integer in the body' => [new Request(request: ['orderId' => 42]), '42'];

        yield 'empty path value does not fall back to the query' => [
            new Request(query: ['orderId' => Uuid::randomHex()], attributes: ['orderId' => '']),
            '',
        ];
    }

    public function testFailsWhenTheOrderRouteDoesNotReturnTheOrder(): void
    {
        $orderId = Uuid::randomHex();

        $orderRoute = $this->createMock(AbstractOrderRoute::class);
        $orderRoute->expects($this->once())
            ->method('load')
            ->willReturn(self::createOrderRouteResponse());

        $this->expectExceptionObject(OrderException::orderNotFound($orderId));

        $this->createListener($orderRoute)->restoreOrderState($this->createEvent($this->createOptedInRequest($orderId)));
    }

    public function testStoresTheOrderBasedStateNextToTheSessionState(): void
    {
        $orderId = Uuid::randomHex();
        $request = $this->createOptedInRequest($orderId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $this->sessionContext->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);

        $orderRoute = $this->createMock(AbstractOrderRoute::class);
        $orderRoute->expects($this->once())
            ->method('load')
            ->willReturnCallback(function (Request $routeRequest, SalesChannelContext $context, Criteria $criteria) use ($request, $orderId): OrderRouteResponse {
                static::assertNotSame($request, $routeRequest);
                static::assertSame([], $routeRequest->query->all());
                static::assertSame([], $routeRequest->request->all());
                static::assertSame($this->sessionContext, $context);
                static::assertEquals($this->orderRestorer->addRequiredAssociations(new Criteria([$orderId])), $criteria);

                return self::createOrderRouteResponse(self::createOrder($orderId));
            });

        $this->createListener($orderRoute)->restoreOrderState($this->createEvent($request));

        static::assertSame($this->restoredContext, $request->attributes->get(PlatformRequest::ATTRIBUTE_EFFECTIVE_SALES_CHANNEL_CONTEXT_OBJECT));
        static::assertSame($this->restoredContext->getContext(), $request->attributes->get(PlatformRequest::ATTRIBUTE_EFFECTIVE_CONTEXT_OBJECT));

        $cart = $request->attributes->get(PlatformRequest::ATTRIBUTE_EFFECTIVE_CART_OBJECT);
        static::assertInstanceOf(Cart::class, $cart);
        static::assertSame($this->restoredContext->getToken(), $cart->getToken());

        static::assertTrue($request->attributes->get(PlatformRequest::ATTRIBUTE_NO_STORE));
        static::assertFalse($request->attributes->has(PlatformRequest::ATTRIBUTE_HTTP_CACHE));
        static::assertSame($orderId, $request->attributes->get('orderId'));

        static::assertSame($this->sessionContext, $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT));
        static::assertSame($this->sessionContext->getContext(), $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT));
    }

    private function createListener(AbstractOrderRoute $orderRoute): OrderRestorationListener
    {
        return new OrderRestorationListener($orderRoute, $this->orderRestorer);
    }

    private function createOptedInRequest(string $orderId, ?SalesChannelContext $context = null): Request
    {
        return new Request(
            query: ['orderId' => $orderId],
            attributes: [
                PlatformRequest::ATTRIBUTE_ALLOW_ORDER_RESTORATION => true,
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $context ?? $this->sessionContext,
            ],
        );
    }

    private function createEvent(Request $request): ControllerEvent
    {
        return new ControllerEvent(
            static::createStub(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    private static function createOrder(string $orderId): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId($orderId);

        return $order;
    }

    private static function createOrderRouteResponse(OrderEntity ...$orders): OrderRouteResponse
    {
        return new OrderRouteResponse(new EntitySearchResult(
            OrderDefinition::ENTITY_NAME,
            \count($orders),
            new OrderCollection($orders),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));
    }
}
