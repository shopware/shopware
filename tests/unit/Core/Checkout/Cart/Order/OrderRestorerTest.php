<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderRestorer;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderRestorer::class)]
class OrderRestorerTest extends TestCase
{
    private OrderEntity $order;

    private Context $context;

    protected function setUp(): void
    {
        $this->order = new OrderEntity();
        $this->order->setId(Uuid::randomHex());

        $this->context = Context::createDefaultContext();
    }

    public function testRestoresContextAndCartWithTheRestorationPermissions(): void
    {
        $restoredContext = Generator::generateSalesChannelContext(token: 'restored-token');
        $cart = new Cart('converted-token');

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->with(
                static::identicalTo($this->order),
                static::identicalTo($this->context),
                [
                    SalesChannelContextService::PERMISSIONS => [
                        CheckoutPermissions::SKIP_PRODUCT_RECALCULATION => true,
                        CheckoutPermissions::SKIP_DELIVERY_PRICE_RECALCULATION => true,
                        CheckoutPermissions::SKIP_PRODUCT_STOCK_VALIDATION => true,
                        CheckoutPermissions::KEEP_INACTIVE_PRODUCT => true,
                        CheckoutPermissions::PIN_MANUAL_PROMOTIONS => true,
                        CheckoutPermissions::PIN_AUTOMATIC_PROMOTIONS => true,
                        CheckoutPermissions::SKIP_CART_PERSISTENCE => true,
                    ],
                ],
            )
            ->willReturn($restoredContext);
        $orderConverter->expects($this->once())
            ->method('convertToCart')
            ->with(static::identicalTo($this->order), static::identicalTo($this->context))
            ->willReturn($cart);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->once())
            ->method('setCart')
            ->with(static::identicalTo($cart));

        $restored = (new OrderRestorer($orderConverter, $cartService))->restore($this->order, $this->context);

        static::assertSame($this->order, $restored->order);
        static::assertSame($restoredContext, $restored->context);
        static::assertSame($cart, $restored->cart);
        static::assertSame('restored-token', $cart->getToken());
    }

    public function testOverrideOptionsWinOverTheRestorationPermissions(): void
    {
        $overrideOptions = [
            SalesChannelContextService::PERMISSIONS => [CheckoutPermissions::SKIP_CART_PERSISTENCE => true],
            SalesChannelContextService::PAYMENT_METHOD_ID => Uuid::randomHex(),
        ];

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->with(static::identicalTo($this->order), static::identicalTo($this->context), $overrideOptions)
            ->willReturn(Generator::generateSalesChannelContext());
        $orderConverter->expects($this->once())
            ->method('convertToCart')
            ->willReturn(new Cart('converted-token'));

        $restorer = new OrderRestorer($orderConverter, static::createStub(CartService::class));
        $restorer->restore($this->order, $this->context, $overrideOptions);
    }

    public function testOrderExceptionsOfTheConversionPassThrough(): void
    {
        $orderConverter = static::createStub(OrderConverter::class);
        $orderConverter->method('assembleSalesChannelContext')->willReturn(Generator::generateSalesChannelContext());
        $orderConverter->method('convertToCart')->willThrowException(OrderException::missingOrderNumber($this->order->getId()));

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->never())->method('setCart');

        $this->expectExceptionObject(OrderException::missingOrderNumber($this->order->getId()));

        (new OrderRestorer($orderConverter, $cartService))->restore($this->order, $this->context);
    }

    public function testOtherFailuresBecomeOrderRestorationFailed(): void
    {
        $cause = CartException::addressNotFound(Uuid::randomHex());

        $orderConverter = static::createStub(OrderConverter::class);
        $orderConverter->method('assembleSalesChannelContext')->willThrowException($cause);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->never())->method('setCart');

        $this->expectExceptionObject(OrderException::orderRestorationFailed($this->order->getId(), $cause));

        try {
            (new OrderRestorer($orderConverter, $cartService))->restore($this->order, $this->context);
        } catch (OrderException $exception) {
            static::assertSame($cause, $exception->getPrevious());

            throw $exception;
        }
    }

    #[DataProvider('requiredAssociationProvider')]
    public function testAddsTheAssociationsTheRestorationNeeds(string $path): void
    {
        $criteria = new Criteria([$this->order->getId()]);

        static::assertSame($criteria, $this->createRestorer()->addRequiredAssociations($criteria));

        $level = $criteria;
        foreach (explode('.', $path) as $association) {
            static::assertArrayHasKey($association, $level->getAssociations(), \sprintf('Association "%s" is missing', $path));
            $level = $level->getAssociations()[$association];
        }
    }

    public static function requiredAssociationProvider(): \Generator
    {
        yield 'line items become the cart line items' => ['lineItems'];
        yield 'order customer resolves the customer of the context' => ['orderCustomer'];
        yield 'primary delivery resolves the shipping method and address of the context' => ['primaryOrderDelivery'];
        yield 'transaction states pick the payment method of the context' => ['transactions.stateMachineState'];
        yield 'deliveries without shipping method are dropped from the cart' => ['deliveries.shippingMethod'];
        yield 'delivery positions need their order line item' => ['deliveries.positions.orderLineItem'];
        yield 'deliveries without shipping country are dropped from the cart' => ['deliveries.shippingOrderAddress.country'];
        yield 'shipping country state completes the shipping location of a delivery' => ['deliveries.shippingOrderAddress.countryState'];
    }

    public function testSortsTransactionsByCreationDateOnlyOnce(): void
    {
        $criteria = new Criteria();
        $restorer = $this->createRestorer();

        $restorer->addRequiredAssociations($criteria);
        $restorer->addRequiredAssociations($criteria);

        static::assertEquals([new FieldSorting('createdAt')], $criteria->getAssociation('transactions')->getSorting());
    }

    public function testKeepsTheTransactionSortingOfTheCaller(): void
    {
        $sorting = new FieldSorting('createdAt', FieldSorting::DESCENDING);
        $criteria = new Criteria();
        $criteria->getAssociation('transactions')->addSorting($sorting);

        $this->createRestorer()->addRequiredAssociations($criteria);

        static::assertSame([$sorting], $criteria->getAssociation('transactions')->getSorting());
    }

    private function createRestorer(): OrderRestorer
    {
        return new OrderRestorer(static::createStub(OrderConverter::class), static::createStub(CartService::class));
    }
}
