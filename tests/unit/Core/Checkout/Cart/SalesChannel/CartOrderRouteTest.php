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
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderProcessor;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Checkout\Payment\PreparedPaymentService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
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

    private CartContextHasher $cartContextHasher;

    private SalesChannelContext $context;

    private CartOrderRoute $route;

    protected function setUp(): void
    {
        $this->cartCalculator = $this->createMock(CartCalculator::class);
        $this->orderRepository = $this->createMock(EntityRepository::class);
        $this->orderPersister = $this->createMock(OrderPersister::class);
        $this->cartContextHasher = new CartContextHasher(new EventDispatcher());

        $this->route = new CartOrderRoute(
            $this->cartCalculator,
            $this->orderRepository,
            $this->orderPersister,
            $this->createMock(AbstractCartPersister::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(PreparedPaymentService::class),
            $this->createMock(PaymentProcessor::class),
            $this->createMock(TaxProviderProcessor::class),
            $this->createMock(AbstractCheckoutGatewayRoute::class),
            $this->cartContextHasher
        );

        $this->context = Generator::createSalesChannelContext();
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

        $this->cartCalculator->expects(static::once())
            ->method('calculate')
            ->with($cart, $this->context)
            ->willReturn($calculatedCart);

        $orderID = 'oder-ID';

        $this->orderPersister->expects(static::once())
            ->method('persist')
            ->with($calculatedCart, $this->context)
            ->willReturn($orderID);

        $orderEntityMock = $this->createMock(EntitySearchResult::class);

        $orderEntity = new OrderEntity();

        $this->orderRepository->expects(static::once())
            ->method('search')
            ->willReturn($orderEntityMock);

        $orderEntityMock->expects(static::once())
            ->method('first')
            ->willReturn($orderEntity);

        $response = $this->route->order($cart, $this->context, $data);

        static::assertInstanceOf(OrderEntity::class, $response->getObject());
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
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

        $this->cartCalculator->expects(static::once())
            ->method('calculate')
            ->with($cart, $this->context)
            ->willReturn($calculatedCart);

        $orderID = 'oder-ID';

        $this->orderPersister->expects(static::once())
            ->method('persist')
            ->with($calculatedCart, $this->context)
            ->willReturn($orderID);

        $orderEntityMock = $this->createMock(EntitySearchResult::class);

        $orderEntity = new OrderEntity();

        $this->orderRepository->expects(static::once())
            ->method('search')
            ->willReturn($orderEntityMock);

        $orderEntityMock->expects(static::once())
            ->method('first')
            ->willReturn($orderEntity);

        $response = $this->route->order($cart, $this->context, $data);

        static::assertInstanceOf(OrderEntity::class, $response->getObject());
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
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
}
