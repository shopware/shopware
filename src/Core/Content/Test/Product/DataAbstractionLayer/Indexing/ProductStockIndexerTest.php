<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class ProductStockIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $lineItemRepository;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var SalesChannelContextFactory
     */
    private $contextFactory;

    /**
     * @var SalesChannelContext
     */
    private $context;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderLineItemRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->orderLineItemRepository = $this->getContainer()->get('order_line_item.repository');
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->lineItemRepository = $this->getContainer()->get('order_line_item.repository');

        $this->context = $this->contextFactory->create(
            Uuid::randomHex(),
            Defaults::SALES_CHANNEL,
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

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(10, $product->getAvailableStock());
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

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getIsCloseout());
        static::assertFalse($product->getAvailable());
        static::assertSame(0, $product->getAvailableStock());
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

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(10, $product->getAvailableStock());

        $this->productRepository->update([['id' => $id, 'stock' => 0]], $context);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getIsCloseout());
        static::assertFalse($product->getAvailable());
        static::assertSame(0, $product->getAvailableStock());
    }

    public function testAvailableAfterOrderProduct(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(5, $product->getAvailableStock());

        $this->orderProduct($id, 1);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(4, $product->getAvailableStock());
        static::assertSame(5, $product->getStock());
    }

    public function testAvailableAfterCancel(): void
    {
        $context = Context::createDefaultContext();
        $initialStock = 12;
        $orderQuantity = 8;

        $productId = $this->createProduct([
            'stock' => $initialStock,
        ]);
        $orderId = $this->orderProduct($productId, $orderQuantity);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();

        static::assertSame($initialStock - $orderQuantity, $product->getAvailableStock());

        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_CANCEL);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();

        static::assertSame($initialStock, $product->getAvailableStock());
    }

    public function testStockUpdatedAfterOrderCompleted(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertSame(5, $product->getStock());

        $orderId = $this->orderProduct($id, 1);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(5, $product->getStock());
        static::assertSame(4, $product->getAvailableStock());

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(4, $product->getStock());
        static::assertSame(4, $product->getAvailableStock());
    }

    public function testUpdateStockWithDifferentOrderVersions(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertSame(5, $product->getStock());

        $orderId = $this->orderProduct($id, 1);

        $this->getContainer()->get('order.repository')
            ->createVersion($orderId, $context);

        $this->getContainer()->get('order.repository')
            ->createVersion($orderId, $context);

        $count = $this->getContainer()
            ->get(Connection::class)
            ->fetchColumn('SELECT COUNT(id) FROM `order` WHERE id = :id', ['id' => Uuid::fromHexToBytes($orderId)]);

        static::assertEquals(3, $count);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(5, $product->getStock());
        static::assertSame(4, $product->getAvailableStock());

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(4, $product->getStock());
        static::assertSame(4, $product->getAvailableStock());
    }

    public function testProductGoesOutOfStock(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertSame(5, $product->getStock());

        $orderId = $this->orderProduct($id, 5);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertFalse($product->getAvailable());
        static::assertSame(5, $product->getStock());
        static::assertSame(0, $product->getAvailableStock());

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertFalse($product->getAvailable());
        static::assertSame(0, $product->getStock());
        static::assertSame(0, $product->getAvailableStock());
    }

    public function testSwitchLineItem(): void
    {
        $id = $this->createProduct();
        $id2 = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertSame(5, $product->getStock());

        $orderId = $this->orderProduct($id, 5);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertFalse($product->getAvailable());
        static::assertSame(5, $product->getStock());
        static::assertSame(0, $product->getAvailableStock());

        $lineItemRepository = $this->getContainer()->get('order_line_item.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('referencedId', $id));
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItem = $lineItemRepository->search($criteria, $context)->first();
        /** @var OrderLineItemEntity $lineItem */
        static::assertInstanceOf(OrderLineItemEntity::class, $lineItem);

        $update = [
            ['id' => $lineItem->getId(), 'referencedId' => $id2, 'productId' => $id2, 'payload' => ['productNumber' => $id2]],
        ];

        $this->lineItemRepository->update($update, $context);

        /** @var EntityCollection $products */
        $products = $context->disableCache(function () use ($id, $id2, $context) {
            return $this->productRepository->search(new Criteria([$id, $id2]), $context);
        });

        $product = $products->get($id);
        static::assertTrue($product->getAvailable());
        static::assertSame(5, $product->getStock());
        static::assertSame(5, $product->getAvailableStock());

        $product = $products->get($id2);
        static::assertFalse($product->getAvailable());
        static::assertSame(5, $product->getStock());
        static::assertSame(0, $product->getAvailableStock());
    }

    public function testDeleteLineItemUpdatesStock(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertSame(5, $product->getStock());

        $orderId = $this->orderProduct($id, 5);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertFalse($product->getAvailable());
        static::assertSame(5, $product->getStock());
        static::assertSame(0, $product->getAvailableStock());

        $lineItemRepository = $this->getContainer()->get('order_line_item.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('referencedId', $id));
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItem = $lineItemRepository->search($criteria, $context)->first();

        /** @var OrderLineItemEntity $lineItem */
        static::assertInstanceOf(OrderLineItemEntity::class, $lineItem);

        $lineItemRepository->delete([
            ['id' => $lineItem->getId()],
        ], $context);

        $product = $context->disableCache(function () use ($id, $context) {
            return $this->productRepository->search(new Criteria([$id]), $context)->get($id);
        });

        static::assertTrue($product->getAvailable());
        static::assertSame(5, $product->getStock());
        static::assertSame(5, $product->getAvailableStock());
    }

    public function testAvailableStockIsUpdatedIfQuantityOfOrderLineItemIsChanged(): void
    {
        $context = Context::createDefaultContext();

        $productId = $this->createProduct([
            'stock' => 5,
        ]);
        $orderId = $this->orderProduct($productId, 1);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertSame(4, $product->getAvailableStock());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        /** @var OrderLineItemEntity $orderLineItem */
        $orderLineItem = $this->orderLineItemRepository->search($criteria, $context)->first();
        $this->orderLineItemRepository->update([
            [
                'id' => $orderLineItem->getId(),
                'quantity' => 2,
            ],
        ], $context);

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertSame(3, $product->getAvailableStock());
    }

    public function testAvailableStockIsUpdatedIfOrderLineItemIsDeleted(): void
    {
        $context = Context::createDefaultContext();

        $productId = $this->createProduct([
            'stock' => 5,
        ]);
        $orderId = $this->orderProduct($productId, 1);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertSame(4, $product->getAvailableStock());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        /** @var OrderLineItemEntity $orderLineItem */
        $orderLineItem = $this->orderLineItemRepository->search($criteria, $context)->first();
        $this->orderLineItemRepository->delete([
            [
                'id' => $orderLineItem->getId(),
            ],
        ], $context);

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertSame(5, $product->getAvailableStock());
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

        /** @var ProductEntity $originalProduct */
        $originalProduct = $this->productRepository->search(new Criteria([$originalProductId]), $context)->first();
        /** @var ProductEntity $newProduct */
        $newProduct = $this->productRepository->search(new Criteria([$newProductId]), $context)->first();
        static::assertSame(4, $originalProduct->getAvailableStock());
        static::assertSame(5, $newProduct->getAvailableStock());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        /** @var OrderLineItemEntity $orderLineItem */
        $orderLineItem = $this->orderLineItemRepository->search($criteria, $context)->first();
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
        static::assertSame(5, $originalProduct->getAvailableStock());
        static::assertSame(4, $newProduct->getAvailableStock());
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

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertSame(4, $product->getStock());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        /** @var OrderLineItemEntity $orderLineItem */
        $orderLineItem = $this->orderLineItemRepository->search($criteria, $context)->first();
        $this->orderLineItemRepository->update([
            [
                'id' => $orderLineItem->getId(),
                'quantity' => 2,
            ],
        ], $context);

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        // only not completed orders are considered by the stock indexer
        static::assertSame(4, $product->getStock());
    }

    public function testOrderCanceled(): void
    {
        $context = Context::createDefaultContext();

        $productId = $this->createProduct([
            'stock' => 5,
        ]);
        $orderId = $this->orderProduct($productId, 1);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertSame(5, $product->getStock());
        static::assertSame(4, $product->getAvailableStock());

        $this->transitionOrder($orderId, 'cancel');

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertSame(5, $product->getStock());
        static::assertSame(5, $product->getAvailableStock());

        $this->transitionOrder($orderId, 'reopen');
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        static::assertSame(5, $product->getStock());
        static::assertSame(4, $product->getAvailableStock());
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
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
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

        $this->getContainer()
            ->get('customer.repository')
            ->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }

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
                    'salesChannelId' => Defaults::SALES_CHANNEL,
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
        $factory = new ProductLineItemFactory();

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        $cart = $this->cartService->add($cart, $factory->create($id, ['quantity' => $quantity]), $this->context);

        static::assertSame($quantity, $cart->get($id)->getQuantity());

        return $this->cartService->order($cart, $this->context);
    }

    private function transitionOrder(string $orderId, string $transition): void
    {
        $registry = $this->getContainer()->get(StateMachineRegistry::class);
        $transitionObject = new Transition('order', $orderId, $transition, 'stateId');

        $registry->transition($transitionObject, Context::createDefaultContext());
    }
}
