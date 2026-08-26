<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Order\Transformer\CartTransformer;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\RuleLoaderResult;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RecalculationService::class)]
class RecalculationServiceTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    private OrderConverter&Stub $orderConverter;

    private CartRuleLoader&Stub $cartRuleLoader;

    private Context $context;

    protected function setUp(): void
    {
        $this->salesChannelContext = static::createStub(SalesChannelContext::class);
        $this->orderConverter = static::createStub(OrderConverter::class);
        $this->orderConverter
            ->method('assembleSalesChannelContext')
            ->willReturnCallback(static function (OrderEntity $order, Context $context) {
                static::assertNotNull($order->getTaxStatus());
                $context->setTaxState($order->getTaxStatus());

                $salesChannel = new SalesChannelEntity();
                $salesChannel->setId(Uuid::randomHex());

                return Generator::generateSalesChannelContext(
                    baseContext: $context,
                    salesChannel: $salesChannel
                );
            });

        $this->cartRuleLoader = static::createStub(CartRuleLoader::class);
        $this->context = Context::createDefaultContext();
    }

    public function testRecalculateOrderWithTaxStatus(): void
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE);

        $orderEntity = $this->orderEntity();
        $orderEntity->setDeliveries(new OrderDeliveryCollection([$this->orderDeliveryEntity()]));
        $cart = $this->getCart();
        $cart->add($lineItem);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$orderEntity]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(static function (array $data, Context $context) use ($orderEntity) {
                static::assertSame($data[0]['stateId'], $orderEntity->getStateId());
                static::assertNotNull($data[0]['deliveries']);
                static::assertNotNull($data[0]['deliveries'][0]);
                if (Feature::isActive('v6.8.0.0')) {
                    static::assertSame($data[0]['deliveries'][0]['stateId'], $orderEntity->getPrimaryOrderDelivery()?->getStateId());
                } else {
                    static::assertSame($data[0]['deliveries'][0]['stateId'], $orderEntity->getDeliveries()?->first()?->getStateId());
                }

                static::assertSame($context->getTaxState(), CartPrice::TAX_STATE_FREE);

                $price = $data[0]['price'];
                self::assertInstanceOf(CartPrice::class, $price);

                static::assertSame($price->getTaxStatus(), CartPrice::TAX_STATE_FREE);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], Context::createDefaultContext()),
                ]), []);
            });

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->method('assembleSalesChannelContext')
            ->willReturnCallback($this->assembleSalesChannelContextCallback());
        $orderConverter
            ->expects($this->once())
            ->method('convertToCart')
            ->willReturnCallback(static function (OrderEntity $order, Context $context) use ($cart) {
                static::assertSame($order->getTaxStatus(), CartPrice::TAX_STATE_FREE);
                static::assertSame($context->getTaxState(), CartPrice::TAX_STATE_FREE);

                return $cart;
            });

        $orderConverter
            ->expects($this->once())
            ->method('convertToOrder')
            ->willReturnCallback(function (Cart $cart, SalesChannelContext $context, OrderConversionContext $conversionContext) {
                $salesChannelContext = $this->createStub(SalesChannelContext::class);
                $salesChannelContext->method('getTaxState')
                    ->willReturn(CartPrice::TAX_STATE_FREE);

                $order = CartTransformer::transform(
                    $cart,
                    $salesChannelContext,
                    '',
                    $conversionContext->shouldIncludePersistentData(),
                );

                // add empty delivery to trigger settings the state id
                if ($conversionContext->shouldIncludeDeliveries()) {
                    $order['deliveries'] = [[
                        'id' => Uuid::randomHex(),
                        'stateId' => 'some-random-state-id',
                        'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    ]];
                }

                return $order;
            });

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader
            ->expects($this->once())
            ->method('loadByCart')
            ->willReturn(
                new RuleLoaderResult(
                    $cart,
                    new RuleCollection()
                )
            );

        $recalculationService = new RecalculationService(
            $entityRepository,
            $orderConverter,
            static::createStub(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            static::createStub(Processor::class),
            $cartRuleLoader,
            static::createStub(PromotionItemBuilder::class),
            $this->lineItemFactoryRegistry()
        );

        $recalculationService->recalculate($orderEntity->getId(), $this->context);
    }

    public function testAddProductToOrder(): void
    {
        $delivery = $this->orderDeliveryEntity();

        $order = $this->orderEntity();
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));
        $order->setPrimaryOrderDeliveryId($delivery->getId());
        $order->setPrimaryOrderDelivery($delivery);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(function (array $data) use ($order) {
                static::assertSame($data[0]['stateId'], $order->getStateId());
                static::assertFalse(isset($data[0]['deliveries']));

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], $this->context),
                ]), []);
            });

        $productEntity = new ProductEntity();
        $productEntity->setId(Uuid::randomHex());

        // We check product existence by searchIds
        /** @var StaticEntityRepository<ProductCollection> */
        $productRepository = new StaticEntityRepository([
            [$productEntity->getId()],
        ]);

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            static::createStub(CartService::class),
            $productRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            static::createStub(Processor::class),
            $this->cartRuleLoader,
            static::createStub(PromotionItemBuilder::class),
            $this->lineItemFactoryRegistry()
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
            ->expects($this->once())
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
            static::createStub(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            static::createStub(Processor::class),
            $this->cartRuleLoader,
            static::createStub(PromotionItemBuilder::class),
            $this->lineItemFactoryRegistry()
        );

        $recalculationService->addCustomLineItem($order->getId(), $lineItem, $this->context);
    }

    public function testAssertProcessorsCalledWithLiveVersion(): void
    {
        $order = $this->orderEntity();
        $order->setDeliveries(new OrderDeliveryCollection([$this->orderDeliveryEntity()]));

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(function (array $data) use ($order) {
                static::assertSame($data[0]['stateId'], $order->getStateId());
                static::assertFalse(isset($data[0]['deliveries']));

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], $this->context),
                ]), []);
            });

        $productEntity = new ProductEntity();
        $productEntity->setId(Uuid::randomHex());

        // We check product existence by searchIds
        /** @var StaticEntityRepository<ProductCollection> */
        $productRepository = new StaticEntityRepository([
            [$productEntity->getId()],
        ]);

        $processor = new LiveProcessorValidator();

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            static::createStub(CartService::class),
            $productRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $processor,
            $this->cartRuleLoader,
            static::createStub(PromotionItemBuilder::class),
            $this->lineItemFactoryRegistry()
        );

        $recalculationService->addProductToOrder($order->getId(), $productEntity->getId(), 1, $this->context);

        static::assertSame(Defaults::LIVE_VERSION, $processor->versionId);
    }

    public function testAddProductToOrderBuildsLineItemWithFactoryRegistry(): void
    {
        $order = $this->orderEntity();

        $entityRepository = static::createStub(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );
        $entityRepository->method('upsert')->willReturn(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([]),
            []
        ));

        $productId = Uuid::randomHex();

        /** @var StaticEntityRepository<ProductCollection> */
        $productRepository = new StaticEntityRepository([[$productId]]);

        // a factory decorator may return a completely different line item type for a product
        $factory = $this->createMock(LineItemFactoryInterface::class);
        $factory->method('supports')->willReturn(true);
        $factory
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $data) use ($productId) {
                static::assertSame($productId, $data['id']);
                static::assertSame($productId, $data['referencedId']);
                static::assertSame(LineItem::PRODUCT_LINE_ITEM_TYPE, $data['type']);
                static::assertSame(2, $data['quantity']);

                return new LineItem($data['id'], 'decorated-type', $data['referencedId'], $data['quantity']);
            });

        $processor = new CartCapturingProcessor();

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverterWithCart($this->getCart()),
            static::createStub(CartService::class),
            $productRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $processor,
            $this->cartRuleLoader,
            static::createStub(PromotionItemBuilder::class),
            $this->lineItemFactoryRegistry($factory)
        );

        $recalculationService->addProductToOrder($order->getId(), $productId, 2, $this->context);

        static::assertNotNull($processor->cart);
        $lineItem = $processor->cart->get($productId);
        static::assertNotNull($lineItem);
        static::assertSame('decorated-type', $lineItem->getType());
    }

    /**
     * @param list<string> $lineItemIdsInOrder
     * @param list<string> $lineItemIdsAfterCalculation
     * @param list<string> $expectedDeliveryPositions
     * @param list<string> $nonGoodLineItemIds
     * @param list<string> $lineItemIdsFromTheOrder
     */
    #[DataProvider('addedLineItemsProvider')]
    public function testAddProductToOrderAddsDeliveryPositionsForTheAddedLineItems(
        string $factoryLineItemId,
        array $lineItemIdsInOrder,
        array $lineItemIdsAfterCalculation,
        array $expectedDeliveryPositions,
        array $nonGoodLineItemIds = [],
        array $lineItemIdsFromTheOrder = []
    ): void {
        $delivery = $this->orderDeliveryEntity();

        $order = $this->orderEntity();
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));
        $order->setPrimaryOrderDeliveryId($delivery->getId());
        $order->setPrimaryOrderDelivery($delivery);

        $entityRepository = static::createStub(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );
        $entityRepository->method('upsert')->willReturn(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([]),
            []
        ));

        $productId = Uuid::randomHex();

        /** @var StaticEntityRepository<ProductCollection> */
        $productRepository = new StaticEntityRepository([[$productId]]);

        $factory = $this->createMock(LineItemFactoryInterface::class);
        $factory->method('supports')->willReturn(true);
        $factory
            ->expects($this->once())
            ->method('create')
            ->willReturn($this->calculatedLineItem($factoryLineItemId));

        $orderCart = $this->getCart();
        foreach ($lineItemIdsInOrder as $id) {
            $orderCart->add($this->calculatedLineItem($id));
        }

        $recalculatedCart = $this->getCart();
        $recalculatedCart->setDeliveries(new DeliveryCollection([$this->cartDelivery()]));
        foreach ($lineItemIdsAfterCalculation as $id) {
            $recalculatedLineItem = $this->calculatedLineItem($id);

            if (\in_array($id, $nonGoodLineItemIds, true)) {
                $recalculatedLineItem->setGood(false);
            }

            if (\in_array($id, $lineItemIdsFromTheOrder, true)) {
                $recalculatedLineItem->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct(Uuid::randomHex()));
            }

            $recalculatedCart->add($recalculatedLineItem);
        }

        $processor = static::createStub(Processor::class);
        $processor->method('process')->willReturn($recalculatedCart);

        $cartRuleLoader = static::createStub(CartRuleLoader::class);
        $cartRuleLoader
            ->method('loadByCart')
            ->willReturn(new RuleLoaderResult($recalculatedCart, new RuleCollection()));

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverterWithCart($orderCart),
            static::createStub(CartService::class),
            $productRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $processor,
            $cartRuleLoader,
            static::createStub(PromotionItemBuilder::class),
            $this->lineItemFactoryRegistry($factory)
        );

        $recalculationService->addProductToOrder($order->getId(), $productId, 1, $this->context);

        $cartDelivery = $recalculatedCart->getDeliveries()->first();
        static::assertNotNull($cartDelivery);
        static::assertSame(
            $expectedDeliveryPositions,
            array_values($cartDelivery->getPositions()->map(static fn (DeliveryPosition $position) => $position->getIdentifier()))
        );
    }

    public static function addedLineItemsProvider(): \Generator
    {
        yield 'the added line item gets the delivery position when the calculation keeps it' => [
            'factoryLineItemId' => 'added-line-item',
            'lineItemIdsInOrder' => [],
            'lineItemIdsAfterCalculation' => ['added-line-item'],
            'expectedDeliveryPositions' => ['added-line-item'],
        ];

        yield 'a line item id derived by a factory is still followed after it merged into a line item of the order' => [
            'factoryLineItemId' => 'derived-line-item',
            'lineItemIdsInOrder' => ['derived-line-item'],
            'lineItemIdsAfterCalculation' => ['derived-line-item'],
            'expectedDeliveryPositions' => ['derived-line-item'],
        ];

        yield 'the line items that replaced the added one get the delivery positions' => [
            'factoryLineItemId' => 'added-line-item',
            'lineItemIdsInOrder' => [],
            'lineItemIdsAfterCalculation' => ['replacement-1', 'replacement-2'],
            'expectedDeliveryPositions' => ['replacement-1', 'replacement-2'],
        ];

        yield 'the line items the calculation added beside the kept one also get the delivery positions' => [
            'factoryLineItemId' => 'added-line-item',
            'lineItemIdsInOrder' => [],
            'lineItemIdsAfterCalculation' => ['added-line-item', 'companion-of-the-calculation'],
            'expectedDeliveryPositions' => ['added-line-item', 'companion-of-the-calculation'],
        ];

        yield 'a line item that is no good, for example a discount the calculation created, gets no delivery position' => [
            'factoryLineItemId' => 'added-line-item',
            'lineItemIdsInOrder' => [],
            'lineItemIdsAfterCalculation' => ['replacement-1', 'discount-of-the-calculation'],
            'expectedDeliveryPositions' => ['replacement-1'],
            'nonGoodLineItemIds' => ['discount-of-the-calculation'],
        ];

        yield 'a line item of the order gets no second delivery position after the calculation re-identified it' => [
            'factoryLineItemId' => 'added-line-item',
            'lineItemIdsInOrder' => ['line-item-of-the-order'],
            'lineItemIdsAfterCalculation' => ['replacement-1', 'line-item-of-the-order-renamed'],
            'expectedDeliveryPositions' => ['replacement-1'],
            'lineItemIdsFromTheOrder' => ['line-item-of-the-order-renamed'],
        ];
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
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects($this->once())
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
            static::createStub(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            static::createStub(Processor::class),
            $this->cartRuleLoader,
            static::createStub(PromotionItemBuilder::class),
            $this->lineItemFactoryRegistry()
        );

        $recalculationService->addPromotionLineItem($order->getId(), '', $this->context);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testToggleAutomaticPromotion(): void
    {
        $order = $this->orderEntity();

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects($this->once())
            ->method('upsert');

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->method('assembleSalesChannelContext')
            ->willReturnCallback($this->assembleSalesChannelContextCallback());
        $orderConverter
            ->expects($this->once())
            ->method('convertToOrder')
            ->with(static::anything(), static::anything(), static::callback(static function (OrderConversionContext $context) {
                return $context->shouldIncludeDeliveries();
            }))
            ->willReturnCallback(static function (Cart $cart, SalesChannelContext $context, OrderConversionContext $conversionContext) {
                return CartTransformer::transform(
                    $cart,
                    $context,
                    '',
                    $conversionContext->shouldIncludePersistentData(),
                );
            });

        $recalculationService = new RecalculationService(
            $entityRepository,
            $orderConverter,
            static::createStub(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            static::createStub(Processor::class),
            $this->cartRuleLoader,
            static::createStub(PromotionItemBuilder::class),
            $this->lineItemFactoryRegistry()
        );

        $recalculationService->toggleAutomaticPromotion($order->getId(), $this->context, false);
    }

    public function testRecalculateOrderWithEmptyLineItems(): void
    {
        $orderEntity = $this->orderEntity();

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$orderEntity]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(static function (array $data) {
                static::assertNotNull($data[0]);
                static::assertEmpty($data[0]['deliveries']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], Context::createDefaultContext()),
                ]), []);
            });

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->method('assembleSalesChannelContext')
            ->willReturnCallback($this->assembleSalesChannelContextCallback());
        $orderConverter
            ->expects($this->once())
            ->method('convertToOrder')
            ->willReturnCallback(function (Cart $cart, SalesChannelContext $context, OrderConversionContext $conversionContext) {
                $salesChannelContext = $this->createStub(SalesChannelContext::class);
                $salesChannelContext->method('getTaxState')
                    ->willReturn(CartPrice::TAX_STATE_FREE);

                return CartTransformer::transform(
                    $cart,
                    $salesChannelContext,
                    '',
                    $conversionContext->shouldIncludePersistentData(),
                );
            });

        $recalculationService = new RecalculationService(
            $entityRepository,
            $orderConverter,
            static::createStub(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            static::createStub(Processor::class),
            $this->cartRuleLoader,
            static::createStub(PromotionItemBuilder::class),
            $this->lineItemFactoryRegistry()
        );

        $recalculationService->recalculate($orderEntity->getId(), $this->context);
    }

    public function testSetCartErrorToValidatedCart(): void
    {
        $order = $this->orderEntity();

        $entityRepository = static::createStub(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $persistentError = $this->createMock(Error::class);
        $persistentError
            ->expects($this->once())
            ->method('isPersistent')
            ->willReturn(true);

        $nonPersistentError = $this->createMock(Error::class);
        $nonPersistentError
            ->expects($this->once())
            ->method('isPersistent')
            ->willReturn(false);

        $cart = new Cart('some-token');
        $cart->setErrors(new ErrorCollection([$persistentError, $nonPersistentError]));

        $processorMock = $this->createMock(Processor::class);
        $processorMock
            ->expects($this->once())
            ->method('process')
            ->willReturn($cart);

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader
            ->expects($this->once())
            ->method('loadByCart')
            ->willReturn(new RuleLoaderResult(new Cart('reloaded-cart'), new RuleCollection()));

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->method('assembleSalesChannelContext')
            ->willReturnCallback($this->assembleSalesChannelContextCallback());
        $orderConverter
            ->expects($this->once())
            ->method('convertToOrder')
            ->willReturnCallback(static function (Cart $validatedCart) {
                static::assertCount(1, $validatedCart->getErrors());
                static::assertInstanceOf(Error::class, $validatedCart->getErrors()->first());

                return [];
            });

        $recalculationService = new RecalculationService(
            $entityRepository,
            $orderConverter,
            static::createStub(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $processorMock,
            $cartRuleLoader,
            static::createStub(PromotionItemBuilder::class),
            $this->lineItemFactoryRegistry()
        );

        $recalculationService->addCustomLineItem($order->getId(), new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE), $this->context);
    }

    /**
     * The assembleSalesChannelContext stub behaviour shared by the OrderConverter mocks
     * built in the tests that set expectations on the converter.
     */
    private function assembleSalesChannelContextCallback(): \Closure
    {
        return static function (OrderEntity $order, Context $context) {
            static::assertNotNull($order->getTaxStatus());
            $context->setTaxState($order->getTaxStatus());

            $salesChannel = new SalesChannelEntity();
            $salesChannel->setId(Uuid::randomHex());

            return Generator::generateSalesChannelContext(
                baseContext: $context,
                salesChannel: $salesChannel
            );
        };
    }

    private function orderConverterWithCart(Cart $cart): OrderConverter&Stub
    {
        $orderConverter = static::createStub(OrderConverter::class);
        $orderConverter
            ->method('assembleSalesChannelContext')
            ->willReturnCallback($this->assembleSalesChannelContextCallback());
        $orderConverter
            ->method('convertToCart')
            ->willReturn($cart);

        return $orderConverter;
    }

    private function calculatedLineItem(string $id): LineItem
    {
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex());
        $lineItem->setStackable(true);
        $lineItem->setShippingCostAware(true);
        $lineItem->setPrice(new CalculatedPrice(1.0, 1.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        return $lineItem;
    }

    private function cartDelivery(): Delivery
    {
        return new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
    }

    private function lineItemFactoryRegistry(?LineItemFactoryInterface $factory = null): LineItemFactoryRegistry
    {
        return new LineItemFactoryRegistry(
            [$factory ?? new ProductLineItemFactory(new PriceDefinitionFactory())],
            static::createStub(DataValidator::class),
            new EventDispatcher()
        );
    }

    private function orderEntity(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setTaxStatus(CartPrice::TAX_STATE_FREE);
        $order->setStateId(Uuid::randomHex());

        if (Feature::isActive('v6.8.0.0')) {
            $deliveryId = Uuid::randomHex();
            $deliveryEntity = new OrderDeliveryEntity();
            $deliveryEntity->setId($deliveryId);
            $deliveryEntity->setStateId(Uuid::randomHex());

            $order->setPrimaryOrderDeliveryId($deliveryId);
            $order->setPrimaryOrderDelivery($deliveryEntity);
        }

        return $order;
    }

    private function orderDeliveryEntity(int $price = 0): OrderDeliveryEntity
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId(Uuid::randomHex());
        $delivery->setStateId(Uuid::randomHex());
        $delivery->setShippingCosts(new CalculatedPrice(
            $price,
            $price,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        ));

        return $delivery;
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
#[Package('checkout')]
class CartCapturingProcessor extends Processor
{
    public ?Cart $cart = null;

    public function __construct()
    {
    }

    public function process(Cart $original, SalesChannelContext $context, CartBehavior $behavior): Cart
    {
        $this->cart = $original;

        return $original;
    }
}

/**
 * @internal
 */
#[Package('checkout')]
class LiveProcessorValidator extends Processor
{
    public ?string $versionId = null;

    public function __construct()
    {
    }

    public function process(Cart $original, SalesChannelContext $context, CartBehavior $behavior): Cart
    {
        TestCase::assertSame(Defaults::LIVE_VERSION, $context->getVersionId());
        $this->versionId = $context->getVersionId();

        return $original;
    }
}
