<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Stock;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductNoLongerAvailableEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(StockStorage::class)]
#[Group('slow')]
class StockStorageTest extends TestCase
{
    use CountryAddToSalesChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    private EntityRepository $productRepository;

    private EntityRepository $lineItemRepository;

    private CartService $cartService;

    private AbstractSalesChannelContextFactory $contextFactory;

    private SalesChannelContext $context;

    private EntityRepository $orderLineItemRepository;

    private EntityRepository $orderRepository;

    protected function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->orderLineItemRepository = $this->getContainer()->get('order_line_item.repository');
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->lineItemRepository = $this->getContainer()->get('order_line_item.repository');
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->addCountriesToSalesChannel();

        $this->context = $this->contextFactory->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
            ]
        );
    }

    public function testAvailableOnInsert(): void
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$product], $context);
        $this->addTaxDataToSalesChannel($this->context, $product['tax']);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());

        $this->assertStock(10, $product);
    }

    public function testAvailableWithoutStock(): void
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 0,
            'isCloseout' => true,
            'name' => 'Test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$product], $context);
        $this->addTaxDataToSalesChannel($this->context, $product['tax']);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getIsCloseout());
        static::assertFalse($product->getAvailable());
        $this->assertStock(0, $product);
    }

    public function testAvailableAfterUpdate(): void
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$product], $context);
        $this->addTaxDataToSalesChannel($this->context, $product['tax']);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(10, $product);

        $this->productRepository->update([['id' => $id, 'stock' => 0]], $context);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getIsCloseout());
        static::assertFalse($product->getAvailable());
        $this->assertStock(0, $product);
    }

    public static function triggerProductNoLongerAvailableEventOnCreateProvider(): \Generator
    {
        yield 'Closeout, no stock' => [0, true, 0];
        yield 'Closeout, with stock' => [1, true, 1];
        yield 'None closeout, no stock' => [0, false, 1];
        yield 'None closeout, stock' => [1, false, 1];
    }

    #[DataProvider('triggerProductNoLongerAvailableEventOnCreateProvider')]
    public function testTriggerProductNoLongerAvailableEventOnCreate(int $stock, bool $closeout, int $triggered): void
    {
        $ids = new IdsCollection();

        $context = Context::createDefaultContext();

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::exactly($triggered))->method('__invoke');

        $this->addEventListener($dispatcher, ProductNoLongerAvailableEvent::class, $listener);

        $product = (new ProductBuilder($ids, 'p1'))
            ->price(10)
            ->stock($stock)
            ->closeout($closeout)
            ->build();

        $this->productRepository->create([$product], $context);
    }

    public static function eventTriggeredOnAlterProvider(): \Generator
    {
        yield 'Closeout, not stock, after 0, not triggered' => [
            'stock' => 0,
            'closeout' => true,
            'after' => 0,
            'triggered' => 0,
        ];

        yield 'Closeout, not stock, after 1, triggered' => [
            'stock' => 0,
            'closeout' => true,
            'after' => 1,
            'triggered' => 1,
        ];

        yield 'Closeout, stock, after 0, triggered' => [
            'stock' => 1,
            'closeout' => true,
            'after' => 0,
            'triggered' => 1,
        ];

        yield 'Closeout, stock, after 1, not triggered' => [
            'stock' => 1,
            'closeout' => true,
            'after' => 1,
            'triggered' => 0,
        ];

        // changing stock of closeout products should never trigger the event
        yield 'None closeout, not stock, after 0, not triggered' => [
            'stock' => 0,
            'closeout' => false,
            'after' => 0,
            'triggered' => 0,
        ];

        yield 'None closeout, not stock, after 1, not triggered' => [
            'stock' => 0,
            'closeout' => false,
            'after' => 1,
            'triggered' => 0,
        ];

        yield 'None closeout, stock, after 0, not triggered' => [
            'stock' => 1,
            'closeout' => false,
            'after' => 0,
            'triggered' => 0,
        ];

        yield 'None closeout, stock, after 1, not triggered' => [
            'stock' => 1,
            'closeout' => false,
            'after' => 1,
            'triggered' => 0,
        ];
    }

    #[DataProvider('eventTriggeredOnAlterProvider')]
    public function testEventTriggeredOnAlter(int $stock, bool $closeout, int $after, int $triggered): void
    {
        $ids = new IdsCollection();

        $context = Context::createDefaultContext();

        $product = (new ProductBuilder($ids, 'p1'))
            ->price(10)
            ->stock($stock)
            ->closeout($closeout)
            ->build();

        $this->productRepository->create([$product], $context);

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();

        $listener->expects(static::exactly($triggered))->method('__invoke');
        $this->addEventListener($dispatcher, ProductNoLongerAvailableEvent::class, $listener);

        $this->productRepository->update([['id' => $product['id'], 'stock' => $after]], $context);
    }

    public function testStockAfterOrderProduct(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(5, $product);

        $this->orderProduct($id, 1);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(4, $product);
    }

    public function testStockAfterCancel(): void
    {
        $context = Context::createDefaultContext();
        $initialStock = 12;
        $orderQuantity = 8;

        $productId = $this->createProduct([
            'stock' => $initialStock,
        ]);
        $orderId = $this->orderProduct($productId, $orderQuantity);

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        $this->assertStock($initialStock - $orderQuantity, $product);

        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_CANCEL);

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        $this->assertStock($initialStock, $product);
    }

    public function testStockNotReduceDuplicatedWhenReOpenOrder(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        $this->assertStock(5, $product);
        static::assertSame(0, $product->getSales());

        $orderId = $this->orderProduct($id, 1);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(4, $product);
        static::assertSame(1, $product->getSales());

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');
        $this->transitionOrder($orderId, 'reopen');
        $this->transitionOrder($orderId, 'process');

        $id2 = $this->createProduct();
        $this->orderProduct($id2, 1);

        $this->transitionOrder($orderId, 'complete');

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(4, $product);
        static::assertSame(1, $product->getSales());
    }

    public function testUpdateStockAndSalesWithDifferentOrderVersions(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        $this->assertStock(5, $product);
        static::assertSame(0, $product->getSales());

        $orderId = $this->orderProduct($id, 1);

        $this->getContainer()->get('order.repository')
            ->createVersion($orderId, $context);

        $this->getContainer()->get('order.repository')
            ->createVersion($orderId, $context);

        $count = $this->getContainer()
            ->get(Connection::class)
            ->fetchOne('SELECT COUNT(id) FROM `order` WHERE id = :id', ['id' => Uuid::fromHexToBytes($orderId)]);

        static::assertEquals(3, $count);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(4, $product);
        static::assertSame(1, $product->getSales());

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(4, $product);
        static::assertSame(1, $product->getSales());
    }

    public function testProductGoesOutOfStock(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        $this->assertStock(5, $product);

        $orderId = $this->orderProduct($id, 5);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->getAvailable());
        $this->assertStock(0, $product);

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->getAvailable());
        $this->assertStock(0, $product);
    }

    public function testSwitchLineItem(): void
    {
        $id = $this->createProduct();
        $id2 = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        $this->assertStock(5, $product);

        $orderId = $this->orderProduct($id, 5);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->getAvailable());
        $this->assertStock(0, $product);

        $lineItemRepository = $this->getContainer()->get('order_line_item.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('referencedId', $id));
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItem = $lineItemRepository->search($criteria, $context)->first();
        static::assertInstanceOf(OrderLineItemEntity::class, $lineItem);

        $update = [
            ['id' => $lineItem->getId(), 'referencedId' => $id2, 'productId' => $id2, 'payload' => ['productNumber' => $id2]],
        ];

        $this->lineItemRepository->update($update, $context);

        /** @var ProductCollection $products */
        $products = $this->productRepository->search(new Criteria([$id, $id2]), $context)->getEntities();

        $product = $products->get($id);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(5, $product);

        $product = $products->get($id2);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->getAvailable());
        $this->assertStock(0, $product);
    }

    public function testStockIsUpdatedIfOrderLineItemIsDeleted(): void
    {
        $context = Context::createDefaultContext();

        $productId = $this->createProduct([
            'stock' => 5,
        ]);
        $orderId = $this->orderProduct($productId, 5);

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->getAvailable());
        $this->assertStock(0, $product);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $orderLineItem = $this->orderLineItemRepository->search($criteria, $context)->first();
        static::assertInstanceOf(OrderLineItemEntity::class, $orderLineItem);

        $this->orderLineItemRepository->delete([
            [
                'id' => $orderLineItem->getId(),
            ],
        ], $context);

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(5, $product);
    }

    public function testAvailableStockIsUpdatedIfProductOfOrderLineItemIsChanged(): void
    {
        $context = Context::createDefaultContext();

        $originalProductId = $this->createProduct([
            'stock' => 5,
        ]);
        $newProductId = $this->createProduct([
            'stock' => 5,
        ]);
        $orderId = $this->orderProduct($originalProductId, 1);

        $originalProduct = $this->productRepository->search(new Criteria([$originalProductId]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $originalProduct);

        $newProduct = $this->productRepository->search(new Criteria([$newProductId]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $newProduct);
        $this->assertStock(4, $originalProduct);
        $this->assertStock(5, $newProduct);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $orderLineItem = $this->orderLineItemRepository->search($criteria, $context)->first();
        static::assertInstanceOf(OrderLineItemEntity::class, $orderLineItem);

        $this->orderLineItemRepository->update([
            [
                'id' => $orderLineItem->getId(),
                'referencedId' => $newProduct->getId(),
                'productId' => $newProduct->getId(),
                'payload' => [
                    'productNumber' => $newProduct->getProductNumber(),
                ],
            ],
        ], $context);

        $newProduct = $this->productRepository->search(new Criteria([$newProductId]), $context)->first();
        $originalProduct = $this->productRepository->search(new Criteria([$originalProductId]), $context)->first();

        static::assertInstanceOf(ProductEntity::class, $newProduct);
        static::assertInstanceOf(ProductEntity::class, $originalProduct);
        $this->assertStock(5, $originalProduct);
        $this->assertStock(4, $newProduct);
    }

    public function testSalesIsUpdatedIfProductOfOrderLineItemIsChanged(): void
    {
        $context = Context::createDefaultContext();

        $originalProductId = $this->createProduct([
            'stock' => 5,
        ]);
        $newProductId = $this->createProduct([
            'stock' => 5,
        ]);

        $orderId = $this->orderProduct($originalProductId, 1);

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        $originalProduct = $this->productRepository->search(new Criteria([$originalProductId]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $originalProduct);

        $newProduct = $this->productRepository->search(new Criteria([$newProductId]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $newProduct);

        static::assertSame(1, $originalProduct->getSales());
        static::assertSame(0, $newProduct->getSales());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $orderLineItem = $this->orderLineItemRepository->search($criteria, $context)->first();
        static::assertInstanceOf(OrderLineItemEntity::class, $orderLineItem);

        $this->orderLineItemRepository->update([
            [
                'id' => $orderLineItem->getId(),
                'referencedId' => $newProduct->getId(),
                'productId' => $newProduct->getId(),
                'payload' => [
                    'productNumber' => $newProduct->getProductNumber(),
                ],
            ],
        ], $context);

        $newProduct = $this->productRepository->search(new Criteria([$newProductId]), $context)->first();
        $originalProduct = $this->productRepository->search(new Criteria([$originalProductId]), $context)->first();

        static::assertInstanceOf(ProductEntity::class, $newProduct);
        static::assertInstanceOf(ProductEntity::class, $originalProduct);
        static::assertSame(0, $originalProduct->getSales());
        static::assertSame(1, $newProduct->getSales());
    }

    public function testStockIsUpdatedIfQuantityOfOrderLineItemIsChanged(): void
    {
        $context = Context::createDefaultContext();

        $productId = $this->createProduct([
            'stock' => 5,
        ]);
        $orderId = $this->orderProduct($productId, 1);
        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        $this->assertStock(4, $product);
        static::assertSame(1, $product->getSales());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $orderLineItem = $this->orderLineItemRepository->search($criteria, $context)->first();
        static::assertInstanceOf(OrderLineItemEntity::class, $orderLineItem);

        $this->orderLineItemRepository->update([
            [
                'id' => $orderLineItem->getId(),
                'quantity' => 2,
            ],
        ], $context);

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        // only not completed orders are considered by the stock indexer
        static::assertInstanceOf(ProductEntity::class, $product);
        $this->assertStock(3, $product);
        static::assertSame(2, $product->getSales());
    }

    public function testOrderCanceled(): void
    {
        $context = Context::createDefaultContext();

        $productId = $this->createProduct([
            'stock' => 5,
        ]);
        $orderId = $this->orderProduct($productId, 1);

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        $this->assertStock(4, $product);

        $this->transitionOrder($orderId, 'cancel');

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        $this->assertStock(5, $product);

        $this->transitionOrder($orderId, 'reopen');

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        $this->assertStock(4, $product);
    }

    public function testSalesIsUpdatedWhenOrderCancelled(): void
    {
        $context = Context::createDefaultContext();

        $productId = $this->createProduct([
            'stock' => 5,
        ]);

        $orderId = $this->orderProduct($productId, 1);

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame(1, $product->getSales());

        $this->transitionOrder($orderId, 'reopen');
        $this->transitionOrder($orderId, 'cancel');

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame(0, $product->getSales());
    }

    public function testSalesIsUpdatedWhenOrderReopened(): void
    {
        $context = Context::createDefaultContext();

        $productId = $this->createProduct([
            'stock' => 5,
        ]);

        $orderId = $this->orderProduct($productId, 1);

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame(1, $product->getSales());

        $this->transitionOrder($orderId, 'reopen');

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame(1, $product->getSales());
    }

    public function testDeleteOrderedProduct(): void
    {
        $context = Context::createDefaultContext();

        $productId = $this->createProduct([
            'stock' => 5,
        ]);

        $this->orderProduct($productId, 1);

        $this->productRepository->delete([
            ['id' => $productId],
        ], $context);
    }

    public function testDeleteOrderIncreasesStock(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(5, $product);

        $orderId = $this->orderProduct($id, 1);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(4, $product);

        $this->orderRepository->delete([['id' => $orderId]], $context);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(5, $product);
    }

    public function testDeleteCancelledOrderDoesNotIncreasesStock(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(5, $product);

        $orderId = $this->orderProduct($id, 1);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(4, $product);

        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_CANCEL);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(5, $product);

        $this->orderRepository->delete([['id' => $orderId]], $context);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->getAvailable());
        $this->assertStock(5, $product);
    }

    private function assertStock(int $expectedStock, ProductEntity $product): void
    {
        static::assertSame($expectedStock, $product->getAvailableStock());
        static::assertSame($expectedStock, $product->getStock());
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'shopware',
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        if (!Feature::isActive('v6.7.0.0')) {
            $customer['defaultPaymentMethodId'] = $this->getValidPaymentMethodId();
        }

        $this->getContainer()
            ->get('customer.repository')
            ->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createProduct(array $config = []): string
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 5,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => [
                [
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $product = array_replace_recursive($product, $config);

        $this->productRepository->create([$product], Context::createDefaultContext());
        $this->addTaxDataToSalesChannel($this->context, $product['tax']);

        return $id;
    }

    private function orderProduct(string $id, int $quantity): string
    {
        $factory = new ProductLineItemFactory(new PriceDefinitionFactory());
        $lineItem = $factory->create(['id' => $id, 'referencedId' => $id, 'quantity' => $quantity], $this->context);

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        $cart = $this->cartService->add($cart, $lineItem, $this->context);

        $item = $cart->get($id);
        static::assertInstanceOf(LineItem::class, $item);
        static::assertSame($quantity, $item->getQuantity());

        return $this->cartService->order($cart, $this->context, new RequestDataBag());
    }

    private function transitionOrder(string $orderId, string $transition): void
    {
        $registry = $this->getContainer()->get(StateMachineRegistry::class);
        $transitionObject = new Transition('order', $orderId, $transition, 'stateId');

        $registry->transition($transitionObject, Context::createDefaultContext());
    }
}
