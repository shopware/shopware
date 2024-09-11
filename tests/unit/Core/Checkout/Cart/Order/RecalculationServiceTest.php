<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Order\Transformer\CartTransformer;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\RuleLoaderResult;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(RecalculationService::class)]
class RecalculationServiceTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    private OrderConverter&MockObject $orderConverter;

    private CartRuleLoader&MockObject $cartRuleLoader;

    private Context $context;

    protected function setUp(): void
    {
        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);
        $this->orderConverter = $this->createMock(OrderConverter::class);
        $this->orderConverter
            ->method('assembleSalesChannelContext')
            ->willReturnCallback(function (OrderEntity $order, Context $context) {
                $context->setTaxState($order->getTaxStatus());

                $salesChannel = new SalesChannelEntity();
                $salesChannel->setId(Uuid::randomHex());

                return new SalesChannelContext(
                    Context::createDefaultContext(),
                    'foo',
                    'bar',
                    $salesChannel,
                    new CurrencyEntity(),
                    new CustomerGroupEntity(),
                    new TaxCollection(),
                    new PaymentMethodEntity(),
                    new ShippingMethodEntity(),
                    new ShippingLocation(new CountryEntity(), null, null),
                    new CustomerEntity(),
                    new CashRoundingConfig(2, 0.01, true),
                    new CashRoundingConfig(2, 0.01, true),
                    []
                );
            });

        $this->cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $this->context = Context::createDefaultContext();
    }

    public function testRecalculateOrderWithTaxStatus(): void
    {
        $deliveryEntity = new OrderDeliveryEntity();
        $deliveryEntity->setId(Uuid::randomHex());
        $deliveryEntity->setStateId(Uuid::randomHex());

        $deliveries = new OrderDeliveryCollection([$deliveryEntity]);

        $orderEntity = $this->orderEntity();
        $orderEntity->setDeliveries($deliveries);
        $cart = $this->getCart();

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$orderEntity]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects(static::once())
            ->method('upsert')
            ->willReturnCallback(function (array $data, Context $context) use ($orderEntity) {
                static::assertSame($data[0]['stateId'], $orderEntity->getStateId());
                static::assertNotNull($data[0]['deliveries']);
                static::assertNotNull($data[0]['deliveries'][0]);
                static::assertSame($data[0]['deliveries'][0]['stateId'], $orderEntity->getDeliveries()?->first()?->getStateId());

                static::assertSame($context->getTaxState(), CartPrice::TAX_STATE_FREE);

                /** @var CartPrice $price */
                $price = $data[0]['price'];

                static::assertSame($price->getTaxStatus(), CartPrice::TAX_STATE_FREE);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], Context::createDefaultContext()),
                ]), []);
            });

        $this->orderConverter
            ->expects(static::once())
            ->method('convertToCart')
            ->willReturnCallback(function (OrderEntity $order, Context $context) use ($cart) {
                static::assertSame($order->getTaxStatus(), CartPrice::TAX_STATE_FREE);
                static::assertSame($context->getTaxState(), CartPrice::TAX_STATE_FREE);

                return $cart;
            });

        $this->orderConverter
            ->expects(static::once())
            ->method('convertToOrder')
            ->willReturnCallback(function (Cart $cart, SalesChannelContext $context, OrderConversionContext $conversionContext) {
                $salesChannelContext = $this->createMock(SalesChannelContext::class);
                $salesChannelContext->method('getTaxState')
                    ->willReturn(CartPrice::TAX_STATE_FREE);

                return CartTransformer::transform(
                    $cart,
                    $salesChannelContext,
                    '',
                    $conversionContext->shouldIncludeOrderDate()
                );
            });

        $this->cartRuleLoader
            ->expects(static::once())
            ->method('loadByCart')
            ->willReturn(
                new RuleLoaderResult(
                    $cart,
                    new RuleCollection()
                )
            );

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            $this->createMock(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $this->createMock(Processor::class),
            $this->cartRuleLoader,
            $this->createMock(PromotionItemBuilder::class)
        );

        $recalculationService->recalculateOrder($orderEntity->getId(), $this->context);
    }

    public function testAddProductToOrder(): void
    {
        $deliveryEntity = new OrderDeliveryEntity();
        $deliveryEntity->setId(Uuid::randomHex());
        $deliveryEntity->setStateId(Uuid::randomHex());

        $deliveries = new OrderDeliveryCollection([$deliveryEntity]);

        $order = $this->orderEntity();
        $order->setDeliveries($deliveries);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects(static::once())
            ->method('upsert')
            ->willReturnCallback(function (array $data) use ($order) {
                static::assertSame($data[0]['stateId'], $order->getStateId());
                static::assertNotNull($data[0]['deliveries']);
                static::assertNotNull($data[0]['deliveries'][0]);
                static::assertSame($data[0]['deliveries'][0]['stateId'], $order->getDeliveries()?->first()?->getStateId());

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], $this->context),
                ]), []);
            });

        $productEntity = new ProductEntity();
        $productEntity->setId(Uuid::randomHex());

        $productRepository = new StaticEntityRepository([
            new ProductCollection([$productEntity]),
        ]);

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            $this->createMock(CartService::class),
            $productRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $this->createMock(Processor::class),
            $this->cartRuleLoader,
            $this->createMock(PromotionItemBuilder::class)
        );

        $recalculationService->addProductToOrder($order->getId(), $productEntity->getId(), 1, $this->context);
    }

    public function testAddCustomLineItem(): void
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE);

        $order = $this->orderEntity();
        $cart = $this->getCart();
        $cart->add($lineItem);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects(static::once())
            ->method('upsert')
            ->willReturnCallback(function (array $data) use ($order) {
                static::assertSame($data[0]['stateId'], $order->getStateId());

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], $this->context),
                ]), []);
            });

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            $this->createMock(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $this->createMock(Processor::class),
            $this->cartRuleLoader,
            $this->createMock(PromotionItemBuilder::class)
        );

        $recalculationService->addCustomLineItem($order->getId(), $lineItem, $this->context);
    }

    public function testAssertProcessorsCalledWithLiveVersion(): void
    {
        $deliveryEntity = new OrderDeliveryEntity();
        $deliveryEntity->setId(Uuid::randomHex());
        $deliveryEntity->setStateId(Uuid::randomHex());

        $deliveries = new OrderDeliveryCollection([$deliveryEntity]);

        $order = $this->orderEntity();
        $order->setDeliveries($deliveries);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects(static::once())
            ->method('upsert')
            ->willReturnCallback(function (array $data) use ($order) {
                static::assertSame($data[0]['stateId'], $order->getStateId());
                static::assertNotNull($data[0]['deliveries']);
                static::assertNotNull($data[0]['deliveries'][0]);
                static::assertSame($data[0]['deliveries'][0]['stateId'], $order->getDeliveries()?->first()?->getStateId());

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], $this->context),
                ]), []);
            });

        $productEntity = new ProductEntity();
        $productEntity->setId(Uuid::randomHex());

        $productRepository = new StaticEntityRepository([
            new ProductCollection([$productEntity]),
        ]);

        $processor = new LiveProcessorValidator();

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            $this->createMock(CartService::class),
            $productRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $processor,
            $this->cartRuleLoader,
            $this->createMock(PromotionItemBuilder::class)
        );

        $recalculationService->addProductToOrder($order->getId(), $productEntity->getId(), 1, $this->context);

        static::assertEquals(Defaults::LIVE_VERSION, $processor->versionId);
    }

    public function testAddPromotionLineItem(): void
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE);

        $order = $this->orderEntity();
        $cart = $this->getCart();
        $cart->add($lineItem);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects(static::once())
            ->method('upsert')
            ->willReturnCallback(function (array $data) use ($order) {
                static::assertSame($data[0]['stateId'], $order->getStateId());

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], $this->context),
                ]), []);
            });

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            $this->createMock(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $this->createMock(Processor::class),
            $this->cartRuleLoader,
            $this->createMock(PromotionItemBuilder::class)
        );

        $recalculationService->addPromotionLineItem($order->getId(), '', $this->context);
    }

    public function testToggleAutomaticPromotion(): void
    {
        $order = $this->orderEntity();

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects(static::once())
            ->method('upsert');

        $this->orderConverter
            ->expects(static::once())
            ->method('convertToOrder')
            ->with(static::anything(), static::anything(), static::callback(function (OrderConversionContext $context) {
                return $context->shouldIncludeDeliveries();
            }));

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            $this->createMock(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $this->createMock(Processor::class),
            $this->cartRuleLoader,
            $this->createMock(PromotionItemBuilder::class)
        );

        $recalculationService->toggleAutomaticPromotion($order->getId(), $this->context, false);
    }

    private function orderEntity(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setTaxStatus(CartPrice::TAX_STATE_FREE);
        $order->setStateId(Uuid::randomHex());

        return $order;
    }

    private function getCart(): Cart
    {
        $cart = new Cart(Uuid::randomHex());

        $cart->setPrice(new CartPrice(
            0.0,
            0.0,
            0.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_FREE
        ));

        return $cart;
    }
}

/**
 * @internal
 */
class LiveProcessorValidator extends Processor
{
    public ?string $versionId = null;

    public function __construct()
    {
    }

    public function process(Cart $original, SalesChannelContext $context, CartBehavior $behavior): Cart
    {
        TestCase::assertEquals(Defaults::LIVE_VERSION, $context->getVersionId());
        $this->versionId = $context->getVersionId();

        return $original;
    }
}
