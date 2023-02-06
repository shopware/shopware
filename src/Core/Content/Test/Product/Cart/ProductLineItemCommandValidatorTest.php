<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class ProductLineItemCommandValidatorTest extends TestCase
{
    use TaxAddToSalesChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use CountryAddToSalesChannelTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $repository;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var AbstractSalesChannelContextFactory
     */
    private $contextFactory;

    private SalesChannelContext $context;

    /**
     * @var EntityRepository
     */
    private $lineItemRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->getContainer()->get('product.repository');
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->lineItemRepository = $this->getContainer()->get('order_line_item.repository');
        $this->addCountriesToSalesChannel();

        $this->context = $this->contextFactory->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
            ]
        );
    }

    public function testOrderProduct(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $orderId = $this->orderProduct($id, 5, $this->context);

        static::assertNotNull($orderId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItems = $this->lineItemRepository->search($criteria, $context);

        static::assertCount(1, $lineItems);
        /** @var OrderLineItemEntity $first */
        $first = $lineItems->first();

        static::assertEquals($id, $first->getReferencedId());
        static::assertEquals($id, $first->getProductId());
        static::assertIsArray($first->getPayload());
        static::assertArrayHasKey('productNumber', $first->getPayload());
        static::assertEquals(LineItem::PRODUCT_LINE_ITEM_TYPE, $first->getType());
    }

    public function testUpdateLineItemQuantity(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $orderId = $this->orderProduct($id, 5, $this->context);

        static::assertNotNull($orderId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItems = $this->lineItemRepository->search($criteria, $context);

        static::assertCount(1, $lineItems);
        /** @var OrderLineItemEntity $first */
        $first = $lineItems->first();

        static::assertEquals($id, $first->getReferencedId());
        static::assertEquals($id, $first->getProductId());
        static::assertIsArray($first->getPayload());
        static::assertArrayHasKey('productNumber', $first->getPayload());

        $this->lineItemRepository->update([
            ['id' => $first->getId(), 'quantity' => 10],
        ], $context);

        $lineItems = $this->lineItemRepository->search($criteria, $context);
        $first = $lineItems->first();

        static::assertEquals($id, $first->getReferencedId());
        static::assertEquals($id, $first->getProductId());
        static::assertArrayHasKey('productNumber', $first->getPayload());
        static::assertEquals(10, $first->getQuantity());
    }

    public function testUpdateFailsIfProductNumberIsMissing(): void
    {
        $id = $this->createProduct();
        $secondId = $this->createProduct();

        $context = Context::createDefaultContext();

        $orderId = $this->orderProduct($id, 5, $this->context);

        static::assertNotNull($orderId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItems = $this->lineItemRepository->search($criteria, $context);

        static::assertCount(1, $lineItems);
        /** @var OrderLineItemEntity $first */
        $first = $lineItems->first();

        static::assertEquals($id, $first->getReferencedId());
        static::assertEquals($id, $first->getProductId());
        static::assertIsArray($first->getPayload());
        static::assertArrayHasKey('productNumber', $first->getPayload());

        static::expectException(WriteException::class);
        static::expectExceptionMessage('To change the product of line item (' . $first->getId() . '), the following properties must also be updated: `productId`, `referenceId`, `payload.productNumber`.');

        $this->lineItemRepository->update([
            ['id' => $first->getId(), 'productId' => $secondId],
        ], $context);
    }

    public function testSwitchLineItemProduct(): void
    {
        $id = $this->createProduct();
        $secondId = $this->createProduct();

        $context = Context::createDefaultContext();

        $orderId = $this->orderProduct($id, 5, $this->context);

        static::assertNotNull($orderId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItems = $this->lineItemRepository->search($criteria, $context);

        static::assertCount(1, $lineItems);
        /** @var OrderLineItemEntity $first */
        $first = $lineItems->first();

        static::assertEquals($id, $first->getReferencedId());
        static::assertEquals($id, $first->getProductId());
        static::assertIsArray($first->getPayload());
        static::assertArrayHasKey('productNumber', $first->getPayload());

        $this->lineItemRepository->update([
            ['id' => $first->getId(), 'productId' => $secondId, 'referencedId' => $secondId, 'payload' => ['productNumber' => $secondId]],
        ], $context);
    }

    private function orderProduct(string $id, int $quantity, SalesChannelContext $context): string
    {
        $factory = new ProductLineItemFactory(new PriceDefinitionFactory());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $cart = $this->cartService->add($cart, $factory->create(['id' => $id, 'referencedId' => $id, 'quantity' => $quantity], $context), $context);

        $item = $cart->get($id);
        static::assertInstanceOf(LineItem::class, $item);
        static::assertSame($quantity, $item->getQuantity());

        return $this->cartService->order($cart, $context, new RequestDataBag());
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

        $this->getContainer()
            ->get('customer.repository')
            ->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }

    /**
     * @param array<string|int, mixed|null> $config
     */
    private function createProduct(array $config = []): string
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => $id,
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

        $this->repository->create([$product], Context::createDefaultContext());
        $this->addTaxDataToSalesChannel($this->context, $product['tax']);

        return $id;
    }
}
