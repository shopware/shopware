<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

class ProductStockIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->getContainer()->get('product.repository');
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

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
        $this->repository->create([$product], $context);
        $this->addTaxDataToSalesChannel($this->context, $product['tax']);

        /** @var ProductEntity $product */
        $product = $this->repository->search(new Criteria([$id]), $context)->get($id);

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
        $this->repository->create([$product], $context);
        $this->addTaxDataToSalesChannel($this->context, $product['tax']);

        /** @var ProductEntity $product */
        $product = $this->repository->search(new Criteria([$id]), $context)->get($id);

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
        $this->repository->create([$product], $context);
        $this->addTaxDataToSalesChannel($this->context, $product['tax']);

        /** @var ProductEntity $product */
        $product = $this->repository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(10, $product->getAvailableStock());

        $this->repository->update([['id' => $id, 'stock' => 0]], $context);

        /** @var ProductEntity $product */
        $product = $this->repository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getIsCloseout());
        static::assertFalse($product->getAvailable());
        static::assertSame(0, $product->getAvailableStock());
    }

    public function testAvailableAfterOrderProduct(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->repository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(5, $product->getAvailableStock());

        $this->orderProduct($id, 1);

        /** @var ProductEntity $product */
        $product = $this->repository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(4, $product->getAvailableStock());
        static::assertSame(5, $product->getStock());
    }

    public function testStockUpdatedAfterOrderCompleted(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->repository->search(new Criteria([$id]), $context)->get($id);

        static::assertSame(5, $product->getStock());

        $orderId = $this->orderProduct($id, 1);

        /** @var ProductEntity $product */
        $product = $this->repository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(5, $product->getStock());
        static::assertSame(4, $product->getAvailableStock());

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        /** @var ProductEntity $product */
        $product = $this->repository->search(new Criteria([$id]), $context)->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(4, $product->getStock());
        static::assertSame(4, $product->getAvailableStock());
    }

    public function testProductGoesOutOfStock(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $product = $this->repository->search(new Criteria([$id]), $context)->get($id);

        static::assertSame(5, $product->getStock());

        $orderId = $this->orderProduct($id, 5);

        /** @var ProductEntity $product */
        $product = $this->repository->search(new Criteria([$id]), $context)->get($id);

        static::assertFalse($product->getAvailable());
        static::assertSame(5, $product->getStock());
        static::assertSame(0, $product->getAvailableStock());

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        /** @var ProductEntity $product */
        $product = $this->repository->search(new Criteria([$id]), $context)->get($id);

        static::assertFalse($product->getAvailable());
        static::assertSame(0, $product->getStock());
        static::assertSame(0, $product->getAvailableStock());
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

        $this->repository->create([$product], Context::createDefaultContext());
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

        /* @var OrderEntity $order */
        $registry->transition(
            new Transition(
                'order',
                $orderId,
                $transition,
                'stateId'
            ),
            Context::createDefaultContext()
        );
    }
}
