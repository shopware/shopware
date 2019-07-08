<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class ProductStockIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->getContainer()->get('product.repository');
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
    }

    public function testAvailableOnInsert()
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
        ];

        $context = Context::createDefaultContext();
        $this->repository->create([$product], $context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $context)
            ->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(10, $product->getAvailableStock());
    }

    public function testAvailableWithoutStock()
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 0,
            'isCloseout' => true,
            'name' => 'Test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
        ];

        $context = Context::createDefaultContext();
        $this->repository->create([$product], $context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $context)
            ->get($id);

        static::assertTrue($product->getIsCloseout());
        static::assertFalse($product->getAvailable());
        static::assertSame(0, $product->getAvailableStock());
    }

    public function testAvailableAfterUpdate()
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
        ];

        $context = Context::createDefaultContext();
        $this->repository->create([$product], $context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $context)
            ->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(10, $product->getAvailableStock());

        $this->repository->update([['id' => $id, 'stock' => 0]], $context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $context)
            ->get($id);

        static::assertTrue($product->getIsCloseout());
        static::assertFalse($product->getAvailable());
        static::assertSame(0, $product->getAvailableStock());
    }

    public function testAvailableAfterOrderProduct()
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 5,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => [
                ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $this->repository->create([$product], Context::createDefaultContext());

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), Context::createDefaultContext())
            ->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(5, $product->getAvailableStock());

        $context = $this->contextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL, [
            SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
        ]);

        $factory = new ProductLineItemFactory();

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $cart = $this->cartService->add($cart, $factory->create($id), $context);

        $this->cartService->order($cart, $context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $context->getContext())
            ->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(4, $product->getAvailableStock());
    }

    public function testStockUpdatedAfterShippedDelivery()
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 5,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => [
                ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $this->repository->create([$product], Context::createDefaultContext());

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), Context::createDefaultContext())
            ->get($id);

        static::assertSame(5, $product->getStock());

        $context = $this->contextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL, [
            SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
        ]);

        $factory = new ProductLineItemFactory();

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $cart = $this->cartService->add($cart, $factory->create($id), $context);

        $orderId = $this->cartService->order($cart, $context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $context->getContext())
            ->get($id);

        static::assertTrue($product->getAvailable());
        static::assertSame(5, $product->getStock());
        static::assertSame(4, $product->getAvailableStock());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));
        $criteria->addAssociationPath('stateMachineState.stateMachine');

        $delivery = $this->getContainer()->get('order_delivery.repository')
            ->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(OrderDeliveryEntity::class, $delivery);

        $registry = $this->getContainer()->get(StateMachineRegistry::class);

        /* @var OrderDeliveryEntity $delivery */
        $registry->transition(
            $delivery->getStateMachineState()->getStateMachine(),
            $delivery->getStateMachineState(),
            'order_delivery',
            $delivery->getId(),
            Context::createDefaultContext(),
            'ship'
        );

        /** @var ProductEntity $product */
        $product = $context->getContext()->disableCache(function (Context $context) use ($id) {
            return $this->repository->search(new Criteria([$id]), $context)->get($id);
        });

        static::assertTrue($product->getAvailable());
        static::assertSame(4, $product->getStock());
        static::assertSame(4, $product->getAvailableStock());
    }

    public function testProductGoesOutOfStock()
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 5,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => [
                ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $this->repository->create([$product], Context::createDefaultContext());

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), Context::createDefaultContext())
            ->get($id);

        static::assertSame(5, $product->getStock());

        $context = $this->contextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL, [
            SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
        ]);

        $factory = new ProductLineItemFactory();

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $cart = $this->cartService->add($cart, $factory->create($id, ['quantity' => 5]), $context);

        static::assertSame(5, $cart->get($id)->getQuantity());

        $orderId = $this->cartService->order($cart, $context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $context->getContext())
            ->get($id);

        static::assertFalse($product->getAvailable());
        static::assertSame(5, $product->getStock());
        static::assertSame(0, $product->getAvailableStock());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));
        $criteria->addAssociationPath('stateMachineState.stateMachine');

        $delivery = $this->getContainer()->get('order_delivery.repository')
            ->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(OrderDeliveryEntity::class, $delivery);

        $registry = $this->getContainer()->get(StateMachineRegistry::class);

        /* @var OrderDeliveryEntity $delivery */
        $registry->transition(
            $delivery->getStateMachineState()->getStateMachine(),
            $delivery->getStateMachineState(),
            'order_delivery',
            $delivery->getId(),
            Context::createDefaultContext(),
            'ship'
        );

        /** @var ProductEntity $product */
        $product = $context->getContext()->disableCache(function (Context $context) use ($id) {
            return $this->repository->search(new Criteria([$id]), $context)->get($id);
        });

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
}
