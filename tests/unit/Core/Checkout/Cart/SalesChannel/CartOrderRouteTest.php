<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
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
    private CartCalculator&MockObject $cartCalculator;

    private EntityRepository&MockObject $orderRepository;

    private OrderPersister&MockObject $orderPersister;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private CartContextHasher $cartContextHasher;

    private SalesChannelContext $context;

    private CartOrderRoute $route;

    private CartLocker&MockObject $cartLocker;

    protected function setUp(): void
    {
        $this->cartCalculator = $this->createMock(CartCalculator::class);
        $this->orderRepository = $this->createMock(EntityRepository::class);
        $this->orderPersister = $this->createMock(OrderPersister::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->cartContextHasher = new CartContextHasher(new EventDispatcher());

        $this->cartLocker = $this->createMock(CartLocker::class);
        $this->cartLocker->method('locked')->willReturnCallback(fn (SalesChannelContext $context, \Closure $closure) => $closure());

        $this->route = new CartOrderRoute(
            $this->cartCalculator,
            $this->orderRepository,
            $this->orderPersister,
            $this->createMock(AbstractCartPersister::class),
            $this->eventDispatcher,
            $this->createMock(PaymentProcessor::class),
            $this->createMock(TaxProviderProcessor::class),
            $this->createMock(AbstractCheckoutGatewayRoute::class),
            $this->cartContextHasher,
            new ExtensionDispatcher(new EventDispatcher()),
            $this->cartLocker
        );

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

        $this->cartCalculator->expects($this->once())
            ->method('calculate')
            ->with($cart, $this->context)
            ->willReturn($calculatedCart);

        $orderID = 'oder-ID';

        $this->orderPersister->expects($this->once())
            ->method('persist')
            ->with($calculatedCart, $this->context)
            ->willReturn($orderID);

        $orderEntityMock = $this->createMock(EntitySearchResult::class);

        $orderEntity = new OrderEntity();
        $orderEntity->setId($orderID);
        $orderCollection = new OrderCollection([$orderEntity]);

        $this->orderRepository->expects($this->once())
            ->method('search')
            ->willReturn($orderEntityMock);

        $orderEntityMock->expects($this->once())
            ->method('getEntities')
            ->willReturn($orderCollection);

        $response = $this->route->order($cart, $this->context, $data);

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

        $this->cartCalculator->expects($this->once())
            ->method('calculate')
            ->with($cart, $this->context)
            ->willReturn($calculatedCart);

        $orderID = 'oder-ID';

        $this->orderPersister->expects($this->once())
            ->method('persist')
            ->with($calculatedCart, $this->context)
            ->willReturn($orderID);

        $orderEntityMock = $this->createMock(EntitySearchResult::class);

        $orderEntity = new OrderEntity();
        $orderEntity->setId($orderID);
        $orderCollection = new OrderCollection([$orderEntity]);

        $this->orderRepository->expects($this->once())
            ->method('search')
            ->willReturn($orderEntityMock);

        $orderEntityMock->expects($this->once())
            ->method('getEntities')
            ->willReturn($orderCollection);

        $this->eventDispatcher->expects($this->exactly(2))
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

        $response = $this->route->order($cart, $this->context, $data);

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

        $this->cartCalculator->expects($this->once())
            ->method('calculate')
            ->with($cart, $this->context)
            ->willReturn($calculatedCart);

        $orderID = 'oder-ID';

        $this->orderPersister->expects($this->once())
            ->method('persist')
            ->with($calculatedCart, $this->context)
            ->willReturn($orderID);

        $orderEntityMock = $this->createMock(EntitySearchResult::class);

        $orderEntity = new OrderEntity();
        $orderEntity->setId($orderID);
        $orderCollection = new OrderCollection([$orderEntity]);

        $this->orderRepository->expects($this->once())
            ->method('search')
            ->willReturn($orderEntityMock);

        $orderEntityMock->expects($this->once())
            ->method('getEntities')
            ->willReturn($orderCollection);

        $response = $this->route->order($cart, $this->context, $data);

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

        $this->route->order($cart, $this->context, $data);
    }

    public function testRouteUsesLock(): void
    {
        $cart = new Cart('token');
        $data = new RequestDataBag();

        $this->cartLocker
            ->expects($this->once())
            ->method('locked')
            ->willReturnCallback(fn (SalesChannelContext $context, \Closure $closure) => $closure());

        $exception = new \Exception('test exception');
        $this->cartCalculator
            ->method('calculate')
            ->willThrowException($exception);

        static::expectExceptionObject($exception);

        $this->route->order($cart, $this->context, $data);
    }

    public function testExtensionIsDispatched(): void
    {
        $cart = new Cart('test');

        $context = Generator::generateSalesChannelContext();

        $dispatcher = new EventDispatcher();
        $extensions = new ExtensionDispatcher($dispatcher);

        $route = new CartOrderRoute(
            $this->cartCalculator,
            $this->orderRepository,
            $this->orderPersister,
            $this->createMock(AbstractCartPersister::class),
            $this->eventDispatcher,
            $this->createMock(PaymentProcessor::class),
            $this->createMock(TaxProviderProcessor::class),
            $this->createMock(AbstractCheckoutGatewayRoute::class),
            $this->cartContextHasher,
            $extensions,
            $this->cartLocker,
        );

        $post = $this->createMock(CallableClass::class);
        $post->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener(ExtensionDispatcher::post(CheckoutPlaceOrderExtension::NAME), $post);

        $dispatcher->addListener(
            ExtensionDispatcher::pre(CheckoutPlaceOrderExtension::NAME),
            function (CheckoutPlaceOrderExtension $extension): void {
                $extension->stopPropagation();

                $extension->result = new OrderPlaceResult(Uuid::randomHex());
            }
        );

        // we don't care about the follow-up order process, the event listener above are already tested
        static::expectException(CartException::class);
        static::expectExceptionMessage('Order payment failed. The order was not stored.');

        $route->order($cart, $context, new RequestDataBag());
    }
}
