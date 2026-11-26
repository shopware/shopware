<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Order;

use Doctrine\DBAL\Connection;
use Faker\Factory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class OrderPersisterTest extends TestCase
{
    use CountryAddToSalesChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    private OrderPersisterInterface $orderPersister;

    private Processor $cartProcessor;

    private OrderConverter $orderConverter;

    private CartSerializationCleaner $serializationCleaner;

    protected function setUp(): void
    {
        $this->orderPersister = static::getContainer()->get(OrderPersister::class);
        $this->cartProcessor = static::getContainer()->get(Processor::class);
        $this->orderConverter = static::getContainer()->get(OrderConverter::class);
        $this->serializationCleaner = static::getContainer()->get(CartSerializationCleaner::class);
    }

    public function testSave(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->add(
            (new LineItem('test', 'test'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        )->add(
            (new LineItem('test2', 'test'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test2')
        );
        $positionByIdentifier = [
            'test' => 1,
            'test2' => 2,
        ];

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('create')
            ->with(
                static::callback(static function (array $payload) use ($positionByIdentifier) {
                    foreach ($payload[0]['lineItems'] as $lineItem) {
                        if ($positionByIdentifier[$lineItem['identifier']] !== $lineItem['position']) {
                            return false;
                        }
                    }

                    return true;
                })
            );
        $order = new OrderEntity();
        $order->setUniqueIdentifier(Uuid::randomHex());
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                'order',
                1,
                new EntityCollection([$order]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $persister = new OrderPersister(
            $repository,
            $this->orderConverter,
            $this->serializationCleaner,
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(StockStorage::class),
        );

        $persister->persist($cart, $this->getSalesChannelContext());
    }

    public function testSaveWithMissingLabel(): void
    {
        $cart = new Cart('a-b-c');
        $cart->add(
            (new LineItem('test', LineItem::CREDIT_LINE_ITEM_TYPE))
                ->setPriceDefinition(new AbsolutePriceDefinition(1))
        );

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $processedCart = $this->cartProcessor->process($cart, $context, new CartBehavior());

        $exception = null;

        try {
            $this->orderPersister->persist($processedCart, $context);
        } catch (CartException $exception) {
        }

        static::assertInstanceOf(CartException::class, $exception);
        static::assertStringContainsString('Line item "test" incomplete. Property "label" missing.', $exception->getMessage());
    }

    public function testConcurrentOrdersCannotOversellCloseoutProduct(): void
    {
        $productId = $this->createProduct(['stock' => 1, 'isCloseout' => true]);

        $this->assertSecondOrderRejected($productId);
    }

    public function testConcurrentOrdersCannotOversellVariantInheritingCloseout(): void
    {
        $parentId = $this->createProduct(['stock' => 10, 'isCloseout' => true]);
        $variantId = $this->createProduct(['parentId' => $parentId, 'stock' => 1]);

        $this->assertSecondOrderRejected($variantId);
    }

    public function testNormalProductWithTooLittleStockCanStillBeOrdered(): void
    {
        $productId = $this->createProduct(['stock' => 1, 'isCloseout' => false]);

        [$first, $second] = $this->calculateConcurrentCarts($productId);

        $this->orderPersister->persist(...$first);
        $this->orderPersister->persist(...$second);

        static::assertSame(-1, $this->getStock($productId));
        static::assertSame(2, $this->countOrders($productId));
    }

    private function assertSecondOrderRejected(string $productId): void
    {
        [$first, $second] = $this->calculateConcurrentCarts($productId);

        $this->orderPersister->persist(...$first);

        $exception = null;

        try {
            $this->orderPersister->persist(...$second);
        } catch (CartException $exception) {
        }

        static::assertInstanceOf(CartException::class, $exception);
        static::assertSame(0, $this->getStock($productId));
        static::assertSame(1, $this->countOrders($productId));
    }

    /**
     * Both carts are calculated while the last unit is still in stock, like two checkouts running at the same time.
     *
     * @return list<array{Cart, SalesChannelContext}>
     */
    private function calculateConcurrentCarts(string $productId): array
    {
        $this->addCountriesToSalesChannel();
        $cartService = static::getContainer()->get(CartService::class);
        $factory = new ProductLineItemFactory(new PriceDefinitionFactory());

        $carts = [];
        for ($i = 0; $i < 2; ++$i) {
            $context = static::getContainer()->get(SalesChannelContextFactory::class)->create(
                Uuid::randomHex(),
                TestDefaults::SALES_CHANNEL,
                [SalesChannelContextService::CUSTOMER_ID => $this->createCustomer()]
            );

            $cart = $cartService->getCart($context->getToken(), $context);
            $cart = $cartService->add($cart, $factory->create(['id' => $productId, 'referencedId' => $productId, 'quantity' => 1], $context), $context);
            static::assertFalse($cart->getErrors()->blockOrder());

            $carts[] = [$cart, $context];
        }

        return $carts;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createProduct(array $config): string
    {
        $id = Uuid::randomHex();
        $tax = ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19];

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        if (isset($config['parentId'])) {
            $product['options'] = [['name' => 'red', 'group' => ['name' => 'color']]];
        } else {
            $product += [
                'name' => 'Test',
                'type' => ProductDefinition::TYPE_PHYSICAL,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => $tax,
                'manufacturer' => ['name' => 'test'],
            ];
        }

        static::getContainer()->get('product.repository')->create([array_replace($product, $config)], Context::createDefaultContext());

        if (!isset($config['parentId'])) {
            $context = static::getContainer()->get(SalesChannelContextFactory::class)
                ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
            $this->addTaxDataToSalesChannel($context, $tax);
        }

        return $id;
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        static::getContainer()->get('customer.repository')->upsert([[
            'id' => $customerId,
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
            'addresses' => [[
                'id' => $addressId,
                'customerId' => $customerId,
                'countryId' => $this->getValidCountryId(),
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'city' => 'Schöppingen',
            ]],
        ]], Context::createDefaultContext());

        return $customerId;
    }

    private function getStock(string $productId): int
    {
        $product = static::getContainer()->get('product.repository')
            ->search(new Criteria([$productId]), Context::createDefaultContext())
            ->get($productId);
        static::assertInstanceOf(ProductEntity::class, $product);

        return $product->getStock();
    }

    private function countOrders(string $productId): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('lineItems.productId', $productId));

        return static::getContainer()->get('order.repository')->search($criteria, Context::createDefaultContext())->getTotal();
    }

    private function getCustomer(): CustomerEntity
    {
        $faker = Factory::create();

        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setId('SWAG-ADDRESS-ID-1');
        $billingAddress->setSalutationId($this->getValidSalutationId());
        $billingAddress->setFirstName($faker->firstName());
        $billingAddress->setLastName($faker->lastName());
        $billingAddress->setStreet($faker->streetAddress());
        $billingAddress->setZipcode($faker->postcode());
        $billingAddress->setCity($faker->city());
        $billingAddress->setCountryId('SWAG-AREA-COUNTRY-ID-1');

        $customer = new CustomerEntity();
        $customer->setId('SWAG-CUSTOMER-ID-1');
        $customer->setDefaultBillingAddress($billingAddress);
        $customer->setEmail('test@example.com');
        $customer->setSalutationId($this->getValidSalutationId());
        $customer->setFirstName($faker->firstName());
        $customer->setLastName($faker->lastName());
        $customer->setCustomerNumber('Test');

        return $customer;
    }

    private function getSalesChannelContext(): MockObject&SalesChannelContext
    {
        $customer = $this->getCustomer();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $context = Context::createDefaultContext();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannelContext->method('getContext')->willReturn($context);

        return $salesChannelContext;
    }
}
