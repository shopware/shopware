<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartContextHasher;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartLocker;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedCriteriaEvent;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Extension\CheckoutPlaceOrderExtension;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Order\OrderPlaceResult;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderProcessor;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartOrderRoute::class)]
class CartOrderRouteTest extends TestCase
{
    private CartCalculator&Stub $cartCalculator;

    /**
     * @var EntityRepository<OrderCollection>&Stub
     */
    private EntityRepository&Stub $orderRepository;

    private OrderPersister&Stub $orderPersister;

    private EventDispatcherInterface&Stub $eventDispatcher;

    private CartContextHasher $cartContextHasher;

    private SalesChannelContext $context;

    private CartLocker&Stub $cartLocker;

    protected function setUp(): void
    {
        $this->cartCalculator = static::createStub(CartCalculator::class);
        $this->orderRepository = static::createStub(EntityRepository::class);
        $this->orderPersister = static::createStub(OrderPersister::class);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->cartContextHasher = new CartContextHasher(new EventDispatcher());

        $this->cartLocker = static::createStub(CartLocker::class);
        $this->cartLocker->method('locked')->willReturnCallback(static fn (SalesChannelContext $context, \Closure $closure) => $closure());

        $this->context = Generator::generateSalesChannelContext();
    }

    public function testOrderResponseWithoutHash(): void
    {
        $cartPrice = new CartPrice(
            15,
            20,
            1,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_FREE
        );

        $cart = new Cart('token');
        $cart->setPrice($cartPrice);
        $cart->add(new LineItem('id', 'type'));

        $data = new RequestDataBag();

        $calculatedCart = new Cart('calculated');

        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator->expects($this->once())
            ->method('calculate')
            ->with($cart, $this->context)
            ->willReturn($calculatedCart);

        $orderID = 'oder-ID';

        $orderPersister = $this->createMock(OrderPersister::class);
        $orderPersister->expects($this->once())
            ->method('persist')
            ->with($calculatedCart, $this->context)
            ->willReturn($orderID);

        $orderEntityMock = static::createStub(EntitySearchResult::class);

        $orderEntity = new OrderEntity();
        $orderEntity->setId($orderID);
        $orderCollection = new OrderCollection([$orderEntity]);

        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository->expects($this->once())
            ->method('search')
            ->willReturn($orderEntityMock);

        $orderEntityMock->method('getEntities')
            ->willReturn($orderCollection);

        $route = $this->buildRoute(
            cartCalculator: $cartCalculator,
            orderRepository: $orderRepository,
            orderPersister: $orderPersister,
        );

        $response = $route->order($cart, $this->context, $data);

        static::assertInstanceOf(OrderEntity::class, $response->getObject());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testCheckoutOrderPlacedEventsDispatched(): void
    {
        $cartPrice = new CartPrice(
            15,
            20,
            1,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_FREE
        );

        $cart = new Cart('token');
        $cart->setPrice($cartPrice);
        $cart->add(new LineItem('id', 'type'));

        $data = new RequestDataBag();

        $calculatedCart = new Cart('calculated');

        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator->expects($this->once())
            ->method('calculate')
            ->with($cart, $this->context)
            ->willReturn($calculatedCart);

        $orderID = 'oder-ID';

        $orderPersister = $this->createMock(OrderPersister::class);
        $orderPersister->expects($this->once())
            ->method('persist')
            ->with($calculatedCart, $this->context)
            ->willReturn($orderID);

        $orderEntityMock = static::createStub(EntitySearchResult::class);

        $orderEntity = new OrderEntity();
        $orderEntity->setId($orderID);
        $orderCollection = new OrderCollection([$orderEntity]);

        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository->expects($this->once())
            ->method('search')
            ->willReturn($orderEntityMock);

        $orderEntityMock->method('getEntities')
            ->willReturn($orderCollection);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with(static::callback(static function ($event) use ($orderID, $orderEntity) {
                if ($event instanceof CheckoutOrderPlacedCriteriaEvent) {
                    return $event->getCriteria()->getIds() === [$orderID];
                }
                if ($event instanceof CheckoutOrderPlacedEvent) {
                    return $event->getOrder() === $orderEntity;
                }

                return false;
            }));

        $route = $this->buildRoute(
            cartCalculator: $cartCalculator,
            orderRepository: $orderRepository,
            orderPersister: $orderPersister,
            eventDispatcher: $eventDispatcher,
        );

        $response = $route->order($cart, $this->context, $data);

        static::assertInstanceOf(OrderEntity::class, $response->getObject());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testOrderResponseWithValidHash(): void
    {
        $cartPrice = new CartPrice(
            15,
            20,
            1,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_FREE
        );

        $cart = new Cart('token');
        $cart->setPrice($cartPrice);
        $cart->add(new LineItem('id', 'type'));
        $cart->setHash($this->cartContextHasher->generate($cart, $this->context));

        $data = new RequestDataBag();
        $data->set('hash', $cart->getHash());

        $calculatedCart = new Cart('calculated');

        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator->expects($this->once())
            ->method('calculate')
            ->with($cart, $this->context)
            ->willReturn($calculatedCart);

        $orderID = 'oder-ID';

        $orderPersister = $this->createMock(OrderPersister::class);
        $orderPersister->expects($this->once())
            ->method('persist')
            ->with($calculatedCart, $this->context)
            ->willReturn($orderID);

        $orderEntityMock = static::createStub(EntitySearchResult::class);

        $orderEntity = new OrderEntity();
        $orderEntity->setId($orderID);
        $orderCollection = new OrderCollection([$orderEntity]);

        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository->expects($this->once())
            ->method('search')
            ->willReturn($orderEntityMock);

        $orderEntityMock->method('getEntities')
            ->willReturn($orderCollection);

        $route = $this->buildRoute(
            cartCalculator: $cartCalculator,
            orderRepository: $orderRepository,
            orderPersister: $orderPersister,
        );

        $response = $route->order($cart, $this->context, $data);

        static::assertInstanceOf(OrderEntity::class, $response->getObject());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testHashMismatchException(): void
    {
        $cartPrice = new CartPrice(
            15,
            20,
            1,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_FREE
        );

        $cart = new Cart('token');
        $cart->setPrice($cartPrice);
        $cart->add(new LineItem('1', 'type'));

        $lineItem = new LineItem('1', 'type');
        $lineItem->addChild(new LineItem('1', 'type'));

        $cartPrice2 = new CartPrice(
            20,
            25,
            1,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_FREE
        );

        $cart2 = new Cart('token2');
        $cart2->setPrice($cartPrice2);
        $cart2->add($lineItem);
        $cart2->add(new LineItem('2', 'type'));

        $data = new RequestDataBag();
        $data->set('hash', $this->cartContextHasher->generate($cart2, $this->context));

        static::expectException(CartException::class);

        $this->buildRoute()->order($cart, $this->context, $data);
    }

    public function testRouteUsesLock(): void
    {
        $cart = new Cart('token');
        $data = new RequestDataBag();

        $cartLocker = $this->createMock(CartLocker::class);
        $cartLocker
            ->expects($this->once())
            ->method('locked')
            ->willReturnCallback(static fn (SalesChannelContext $context, \Closure $closure) => $closure());

        $exception = new \Exception('test exception');
        $this->cartCalculator
            ->method('calculate')
            ->willThrowException($exception);

        static::expectExceptionObject($exception);

        $this->buildRoute(cartLocker: $cartLocker)->order($cart, $this->context, $data);
    }

    public function testOrderIsPlacedWhenTheStoredCartStillExists(): void
    {
        $cart = new Cart('token');
        $cart->setPersisted(true);
        $cart->add(new LineItem('id', 'type'));

        $calculatedCart = new Cart('calculated');

        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator->expects($this->once())
            ->method('calculate')
            ->with($cart, $this->context)
            ->willReturn($calculatedCart);

        $orderId = 'order-id';

        $orderPersister = $this->createMock(OrderPersister::class);
        $orderPersister->expects($this->once())
            ->method('persist')
            ->with($calculatedCart, $this->context)
            ->willReturn($orderId);

        $orderEntity = new OrderEntity();
        $orderEntity->setId($orderId);

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new OrderCollection([$orderEntity]));

        $orderRepository = $this->createMock(EntityRepository::class);
        $orderRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $cartPersister = $this->createMock(AbstractCartPersister::class);
        $cartPersister->expects($this->once())
            ->method('exists')
            ->with('token', $this->context)
            ->willReturn(true);

        $route = $this->buildRoute(
            cartCalculator: $cartCalculator,
            orderRepository: $orderRepository,
            orderPersister: $orderPersister,
            cartPersister: $cartPersister,
        );

        $response = $route->order($cart, $this->context, new RequestDataBag());

        static::assertSame($orderEntity, $response->getObject());
    }

    public function testOrderIsRejectedWhenTheStoredCartWasAlreadyConsumed(): void
    {
        $cart = new Cart('token');
        $cart->setPersisted(true);
        $cart->add(new LineItem('id', 'type'));

        $cartPersister = $this->createMock(AbstractCartPersister::class);
        $cartPersister->method('exists')
            ->with('token', $this->context)
            ->willReturn(false);
        $cartPersister->expects($this->never())->method('delete');

        $orderPersister = $this->createMock(OrderPersister::class);
        $orderPersister->expects($this->never())->method('persist');

        $route = $this->buildRoute(orderPersister: $orderPersister, cartPersister: $cartPersister);

        $this->expectExceptionObject(CartException::tokenNotFound('token'));

        $route->order($cart, $this->context, new RequestDataBag());
    }

    public function testExtensionIsDispatched(): void
    {
        $cart = new Cart('test');

        $context = Generator::generateSalesChannelContext();

        $dispatcher = new EventDispatcher();
        $extensions = new ExtensionDispatcher($dispatcher);

        $route = $this->buildRoute(extensions: $extensions);

        $post = $this->createMock(CallableClass::class);
        $post->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener(CheckoutPlaceOrderExtension::onPost(), $post);

        $dispatcher->addListener(
            CheckoutPlaceOrderExtension::onPre(),
            static function (CheckoutPlaceOrderExtension $extension): void {
                $extension->stopPropagation();

                $extension->result = new OrderPlaceResult(Uuid::randomHex());
            }
        );

        // we don't care about the follow-up order process, the event listener above are already tested
        $this->expectExceptionObject(CartException::invalidPaymentOrderNotStored(Uuid::randomHex()));

        $route->order($cart, $context, new RequestDataBag());
    }

    /**
     * @param (EntityRepository<OrderCollection>&MockObject)|null $orderRepository
     */
    private function buildRoute(
        ?CartCalculator $cartCalculator = null,
        ?EntityRepository $orderRepository = null,
        ?OrderPersister $orderPersister = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?CartLocker $cartLocker = null,
        ?ExtensionDispatcher $extensions = null,
        ?AbstractCartPersister $cartPersister = null,
    ): CartOrderRoute {
        return new CartOrderRoute(
            $cartCalculator ?? $this->cartCalculator,
            $orderRepository ?? $this->orderRepository,
            $orderPersister ?? $this->orderPersister,
            $cartPersister ?? static::createStub(AbstractCartPersister::class),
            $eventDispatcher ?? $this->eventDispatcher,
            static::createStub(PaymentProcessor::class),
            static::createStub(TaxProviderProcessor::class),
            static::createStub(AbstractCheckoutGatewayRoute::class),
            $this->cartContextHasher,
            $extensions ?? new ExtensionDispatcher(new EventDispatcher()),
            $cartLocker ?? $this->cartLocker,
        );
    }
}
