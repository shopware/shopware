<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\BaseSalesChannelContext;
use Shopware\Core\System\SalesChannel\Context\AbstractBaseSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelContextFactory::class)]
class SalesChannelContextFactoryTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testCustomerPaymentMethodIsOnlyUsedIfActive(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $basePaymentMethod = new PaymentMethodEntity();
        $basePaymentMethod->setId(Uuid::randomHex());

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setActive(true);
        $customer->setLastPaymentMethodId(Uuid::randomHex());
        $customer->setDefaultBillingAddressId(Uuid::randomHex());
        $customer->setDefaultShippingAddressId(Uuid::randomHex());
        $customer->setGroupId(Uuid::randomHex());

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setFactor(1);

        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setId($customer->getDefaultBillingAddressId());
        $shippingAddress = new CustomerAddressEntity();
        $shippingAddress->setId($customer->getDefaultShippingAddressId());
        $shippingAddress->setCountry($country);
        $addresses = new CustomerAddressCollection([$billingAddress, $shippingAddress]);

        $baseContext = new BaseSalesChannelContext(
            Context::createDefaultContext(new SalesChannelApiSource($salesChannel->getId())),
            $salesChannel,
            $currency,
            new CustomerGroupEntity(),
            new TaxCollection(),
            $basePaymentMethod,
            new ShippingMethodEntity(),
            new ShippingLocation($country, null, null),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            Generator::createLanguageInfo(),
            MeasurementUnits::createDefaultUnits()
        );

        $paymentMethodRepository = new StaticEntityRepository(
            [
                static function (Criteria $criteria, Context $context) use ($baseContext) {
                    static::assertCount(2, $criteria->getFilters());
                    static::assertEquals([
                        new EqualsFilter('active', 1),
                        new EqualsFilter('salesChannels.id', $baseContext->getSalesChannelId()),
                    ], $criteria->getFilters());

                    return new EntitySearchResult(
                        PaymentMethodDefinition::ENTITY_NAME,
                        0,
                        new PaymentMethodCollection(),
                        null,
                        $criteria,
                        $context
                    );
                },
            ],
            new PaymentMethodDefinition(),
        );

        $customerRepository = new StaticEntityRepository(
            [
                static function (Criteria $criteria, Context $context) use ($customer) {
                    return new EntitySearchResult(
                        CustomerDefinition::ENTITY_NAME,
                        1,
                        new CustomerCollection([$customer]),
                        null,
                        $criteria,
                        $context
                    );
                },
            ],
            new CustomerDefinition(),
        );

        $addressRepository = new StaticEntityRepository(
            [
                static function (Criteria $criteria, Context $context) use ($addresses) {
                    return new EntitySearchResult(
                        CustomerAddressDefinition::ENTITY_NAME,
                        2,
                        $addresses,
                        null,
                        $criteria,
                        $context
                    );
                },
            ],
            new CustomerAddressDefinition(),
        );

        $options = [
            SalesChannelContextService::CUSTOMER_ID => $customer->getId(),
        ];

        $baseSalesChannelContextFactory = $this->createMock(AbstractBaseSalesChannelContextFactory::class);
        $baseSalesChannelContextFactory
            ->expects($this->once())
            ->method('create')
            ->with($salesChannel->getId(), $options)
            ->willReturn($baseContext);

        $factory = new SalesChannelContextFactory(
            $customerRepository,
            static::createStub(EntityRepository::class),
            $addressRepository,
            $paymentMethodRepository,
            static::createStub(TaxDetector::class),
            [],
            static::createStub(EventDispatcherInterface::class),
            static::createStub(EntityRepository::class),
            $baseSalesChannelContextFactory,
            static::createStub(EntityRepository::class),
        );

        $generatedContext = $factory->create(Uuid::randomHex(), $salesChannel->getId(), $options);
        static::assertSame($generatedContext->getPaymentMethod(), $baseContext->getPaymentMethod());
    }

    public function testCustomerIsNullIfInactive(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $basePaymentMethod = new PaymentMethodEntity();
        $basePaymentMethod->setId(Uuid::randomHex());

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setActive(false);
        $customer->setLastPaymentMethodId(Uuid::randomHex());
        $customer->setDefaultBillingAddressId(Uuid::randomHex());
        $customer->setDefaultShippingAddressId(Uuid::randomHex());
        $customer->setGroupId(Uuid::randomHex());

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setFactor(1);

        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setId($customer->getDefaultBillingAddressId());
        $shippingAddress = new CustomerAddressEntity();
        $shippingAddress->setId($customer->getDefaultShippingAddressId());
        $shippingAddress->setCountry($country);
        $addresses = new CustomerAddressCollection([$billingAddress, $shippingAddress]);

        $baseContext = new BaseSalesChannelContext(
            Context::createDefaultContext(new SalesChannelApiSource($salesChannel->getId())),
            $salesChannel,
            $currency,
            new CustomerGroupEntity(),
            new TaxCollection(),
            $basePaymentMethod,
            new ShippingMethodEntity(),
            new ShippingLocation($country, null, null),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            Generator::createLanguageInfo(),
            MeasurementUnits::createDefaultUnits()
        );

        $options = [
            SalesChannelContextService::CUSTOMER_ID => $customer->getId(),
        ];

        $baseSalesChannelContextFactory = $this->createMock(AbstractBaseSalesChannelContextFactory::class);
        $baseSalesChannelContextFactory
            ->expects($this->once())
            ->method('create')
            ->with($salesChannel->getId(), $options)
            ->willReturn($baseContext);

        $customerRepository = new StaticEntityRepository(
            [
                static function (Criteria $criteria, Context $context) use ($customer) {
                    return new EntitySearchResult(
                        CustomerDefinition::ENTITY_NAME,
                        1,
                        new CustomerCollection([$customer]),
                        null,
                        $criteria,
                        $context
                    );
                },
            ],
            new CustomerDefinition(),
        );

        $addressRepository = new StaticEntityRepository(
            [
                static function (Criteria $criteria, Context $context) use ($addresses) {
                    return new EntitySearchResult(
                        CustomerAddressDefinition::ENTITY_NAME,
                        2,
                        $addresses,
                        null,
                        $criteria,
                        $context
                    );
                },
            ],
            new CustomerAddressDefinition(),
        );

        $factory = new SalesChannelContextFactory(
            $customerRepository,
            static::createStub(EntityRepository::class),
            $addressRepository,
            static::createStub(EntityRepository::class),
            static::createStub(TaxDetector::class),
            [],
            static::createStub(EventDispatcherInterface::class),
            static::createStub(EntityRepository::class),
            $baseSalesChannelContextFactory,
            static::createStub(EntityRepository::class),
        );

        $generatedContext = $factory->create(Uuid::randomHex(), $salesChannel->getId(), $options);
        static::assertNull($generatedContext->getCustomer());
    }

    public function testCustomerIsSetIfActive(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $basePaymentMethod = new PaymentMethodEntity();
        $basePaymentMethod->setId(Uuid::randomHex());

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setActive(true);
        $customer->setLastPaymentMethodId(Uuid::randomHex());
        $customer->setDefaultBillingAddressId(Uuid::randomHex());
        $customer->setDefaultShippingAddressId(Uuid::randomHex());
        $customer->setGroupId(Uuid::randomHex());

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setFactor(1);

        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setId($customer->getDefaultBillingAddressId());
        $shippingAddress = new CustomerAddressEntity();
        $shippingAddress->setId($customer->getDefaultShippingAddressId());
        $shippingAddress->setCountry($country);
        $addresses = new CustomerAddressCollection([$billingAddress, $shippingAddress]);

        $baseContext = new BaseSalesChannelContext(
            Context::createDefaultContext(new SalesChannelApiSource($salesChannel->getId())),
            $salesChannel,
            $currency,
            new CustomerGroupEntity(),
            new TaxCollection(),
            $basePaymentMethod,
            new ShippingMethodEntity(),
            new ShippingLocation($country, null, null),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            Generator::createLanguageInfo(),
            MeasurementUnits::createDefaultUnits()
        );

        $options = [
            SalesChannelContextService::CUSTOMER_ID => $customer->getId(),
        ];

        $baseSalesChannelContextFactory = $this->createMock(AbstractBaseSalesChannelContextFactory::class);
        $baseSalesChannelContextFactory
            ->expects($this->once())
            ->method('create')
            ->with($salesChannel->getId(), $options)
            ->willReturn($baseContext);

        $customerRepository = new StaticEntityRepository(
            [
                static function (Criteria $criteria, Context $context) use ($customer) {
                    return new EntitySearchResult(
                        CustomerDefinition::ENTITY_NAME,
                        1,
                        new CustomerCollection([$customer]),
                        null,
                        $criteria,
                        $context
                    );
                },
            ],
            new CustomerDefinition(),
        );

        $addressRepository = new StaticEntityRepository(
            [
                static function (Criteria $criteria, Context $context) use ($addresses) {
                    return new EntitySearchResult(
                        CustomerAddressDefinition::ENTITY_NAME,
                        2,
                        $addresses,
                        null,
                        $criteria,
                        $context
                    );
                },
            ],
            new CustomerAddressDefinition(),
        );

        $factory = new SalesChannelContextFactory(
            $customerRepository,
            static::createStub(EntityRepository::class),
            $addressRepository,
            static::createStub(EntityRepository::class),
            static::createStub(TaxDetector::class),
            [],
            static::createStub(EventDispatcherInterface::class),
            static::createStub(EntityRepository::class),
            $baseSalesChannelContextFactory,
            static::createStub(EntityRepository::class),
        );

        $generatedContext = $factory->create(Uuid::randomHex(), $salesChannel->getId(), $options);
        static::assertSame($customer, $generatedContext->getCustomer());
    }

    public function testOrderShippingAddressTakesPrecedenceOverTheCustomerShippingAddress(): void
    {
        $orderShippingAddress = $this->createOrderAddress();

        [$context, $customerShippingAddress] = $this->createContextForOrderShippingAddress(
            new OrderAddressCollection([$orderShippingAddress]),
            [SalesChannelContextService::SHIPPING_ORDER_ADDRESS_ID => $orderShippingAddress->getId()],
        );

        $shippingLocationAddress = $context->getShippingLocation()->getAddress();
        static::assertNotNull($shippingLocationAddress);
        static::assertSame($orderShippingAddress->getId(), $shippingLocationAddress->getId());
        static::assertSame('48624', $shippingLocationAddress->getZipcode());
        static::assertSame('order-shipping-address-hash', $shippingLocationAddress->getHash());
        static::assertSame($context->getCustomerId(), $shippingLocationAddress->getCustomerId());
        static::assertSame($orderShippingAddress->getCountry(), $context->getShippingLocation()->getCountry());
        static::assertSame($customerShippingAddress, $context->getCustomer()?->getActiveShippingAddress());
    }

    public function testExplicitCustomerShippingAddressTakesPrecedenceOverTheOrderShippingAddress(): void
    {
        $orderShippingAddress = $this->createOrderAddress();

        [$context, $customerShippingAddress] = $this->createContextForOrderShippingAddress(
            new OrderAddressCollection([$orderShippingAddress]),
            [
                SalesChannelContextService::SHIPPING_ADDRESS_ID => $this->ids->get('customer-shipping-address'),
                SalesChannelContextService::SHIPPING_ORDER_ADDRESS_ID => $orderShippingAddress->getId(),
            ],
        );

        static::assertSame($customerShippingAddress, $context->getShippingLocation()->getAddress());
    }

    #[DataProvider('unusableOrderShippingAddressProvider')]
    public function testCustomerShippingAddressIsKeptWhenTheOrderShippingAddressIsUnusable(OrderAddressCollection $orderAddresses, string $orderAddressId): void
    {
        [$context, $customerShippingAddress] = $this->createContextForOrderShippingAddress(
            $orderAddresses,
            [SalesChannelContextService::SHIPPING_ORDER_ADDRESS_ID => $orderAddressId],
        );

        static::assertSame($customerShippingAddress, $context->getShippingLocation()->getAddress());
        static::assertSame($customerShippingAddress->getCountry(), $context->getShippingLocation()->getCountry());
    }

    public static function unusableOrderShippingAddressProvider(): \Generator
    {
        yield 'order shipping address does not exist' => [new OrderAddressCollection(), Uuid::randomHex()];

        $addressWithoutCountry = new OrderAddressEntity();
        $addressWithoutCountry->setId(Uuid::randomHex());

        yield 'order shipping address has no country' => [new OrderAddressCollection([$addressWithoutCountry]), $addressWithoutCountry->getId()];
    }

    /**
     * @param list<'billing'|'shipping'> $danglingDefaults
     * @param 'shipping-address'|'billing-address'|'sales-channel' $expectedShippingLocation
     */
    #[DataProvider('danglingDefaultAddressProvider')]
    public function testCustomerWithDanglingDefaultAddressDoesNotThrow(array $danglingDefaults, bool $expectsBillingAddress, bool $expectsShippingAddress, string $expectedShippingLocation): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setActive(true);
        $customer->setDefaultBillingAddressId(Uuid::randomHex());
        $customer->setDefaultShippingAddressId(Uuid::randomHex());
        $customer->setGroupId(Uuid::randomHex());

        $billingCountry = new CountryEntity();
        $billingCountry->setId(Uuid::randomHex());
        $shippingCountry = new CountryEntity();
        $shippingCountry->setId(Uuid::randomHex());
        $salesChannelCountry = new CountryEntity();
        $salesChannelCountry->setId(Uuid::randomHex());
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setFactor(1);
        $currency->setItemRounding(new CashRoundingConfig(2, 0.01, true));
        $currency->setTotalRounding(new CashRoundingConfig(2, 0.01, true));

        $addresses = new CustomerAddressCollection();

        if (!\in_array('billing', $danglingDefaults, true)) {
            $billingAddress = new CustomerAddressEntity();
            $billingAddress->setId($customer->getDefaultBillingAddressId());
            $billingAddress->setCountry($billingCountry);
            $addresses->add($billingAddress);
        }

        if (!\in_array('shipping', $danglingDefaults, true)) {
            $shippingAddress = new CustomerAddressEntity();
            $shippingAddress->setId($customer->getDefaultShippingAddressId());
            $shippingAddress->setCountry($shippingCountry);
            $addresses->add($shippingAddress);
        }

        $baseShippingLocation = new ShippingLocation($salesChannelCountry, null, null);

        $baseContext = new BaseSalesChannelContext(
            Context::createDefaultContext(new SalesChannelApiSource($salesChannel->getId())),
            $salesChannel,
            $currency,
            new CustomerGroupEntity(),
            new TaxCollection(),
            new PaymentMethodEntity(),
            new ShippingMethodEntity(),
            $baseShippingLocation,
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            Generator::createLanguageInfo(),
            MeasurementUnits::createDefaultUnits()
        );

        $customerRepository = new StaticEntityRepository(
            [
                static fn (Criteria $criteria, Context $context) => new EntitySearchResult(
                    CustomerDefinition::ENTITY_NAME,
                    1,
                    new CustomerCollection([$customer]),
                    null,
                    $criteria,
                    $context
                ),
            ],
            new CustomerDefinition(),
        );

        $addressRepository = new StaticEntityRepository(
            [
                static fn (Criteria $criteria, Context $context) => new EntitySearchResult(
                    CustomerAddressDefinition::ENTITY_NAME,
                    $addresses->count(),
                    $addresses,
                    null,
                    $criteria,
                    $context
                ),
            ],
            new CustomerAddressDefinition(),
        );

        $options = [
            SalesChannelContextService::CUSTOMER_ID => $customer->getId(),
        ];

        $baseSalesChannelContextFactory = $this->createMock(AbstractBaseSalesChannelContextFactory::class);
        $baseSalesChannelContextFactory
            ->expects($this->once())
            ->method('create')
            ->with($salesChannel->getId(), $options)
            ->willReturn($baseContext);

        $factory = new SalesChannelContextFactory(
            $customerRepository,
            static::createStub(EntityRepository::class),
            $addressRepository,
            static::createStub(EntityRepository::class),
            static::createStub(TaxDetector::class),
            [],
            static::createStub(EventDispatcherInterface::class),
            static::createStub(EntityRepository::class),
            $baseSalesChannelContextFactory,
            static::createStub(EntityRepository::class),
        );

        $generatedContext = $factory->create(Uuid::randomHex(), $salesChannel->getId(), $options);

        $generatedCustomer = $generatedContext->getCustomer();
        static::assertNotNull($generatedCustomer);
        static::assertSame($expectsBillingAddress, $generatedCustomer->getActiveBillingAddress() !== null);
        static::assertSame($expectsShippingAddress, $generatedCustomer->getActiveShippingAddress() !== null);

        $expectedCountry = match ($expectedShippingLocation) {
            'shipping-address' => $shippingCountry,
            'billing-address' => $billingCountry,
            'sales-channel' => $salesChannelCountry,
        };

        static::assertSame($expectedCountry, $generatedContext->getShippingLocation()->getCountry());
    }

    /**
     * @return iterable<string, array{list<'billing'|'shipping'>, bool, bool, 'shipping-address'|'billing-address'|'sales-channel'}>
     */
    public static function danglingDefaultAddressProvider(): iterable
    {
        yield 'dangling default billing address' => [['billing'], false, true, 'shipping-address'];
        yield 'dangling default shipping address' => [['shipping'], true, false, 'billing-address'];
        yield 'both default addresses dangling' => [['billing', 'shipping'], false, false, 'sales-channel'];
    }

    private function createOrderAddress(): OrderAddressEntity
    {
        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());

        $orderAddress = new OrderAddressEntity();
        $orderAddress->setId(Uuid::randomHex());
        $orderAddress->setFirstName('Max');
        $orderAddress->setLastName('Mustermann');
        $orderAddress->setStreet('Ebbinghoff 10');
        $orderAddress->setZipcode('48624');
        $orderAddress->setCity('Schöppingen');
        $orderAddress->setHash('order-shipping-address-hash');
        $orderAddress->setCountryId($country->getId());
        $orderAddress->setCountry($country);

        return $orderAddress;
    }

    /**
     * @param array<string, string> $options
     *
     * @return array{SalesChannelContext, CustomerAddressEntity}
     */
    private function createContextForOrderShippingAddress(OrderAddressCollection $orderAddresses, array $options): array
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setActive(true);
        $customer->setDefaultBillingAddressId(Uuid::randomHex());
        $customer->setDefaultShippingAddressId($this->ids->get('customer-shipping-address'));
        $customer->setGroupId(Uuid::randomHex());

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setFactor(1);
        $currency->setItemRounding(new CashRoundingConfig(2, 0.01, true));
        $currency->setTotalRounding(new CashRoundingConfig(2, 0.01, true));

        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setId($customer->getDefaultBillingAddressId());
        $shippingAddress = new CustomerAddressEntity();
        $shippingAddress->setId($this->ids->get('customer-shipping-address'));
        $shippingAddress->setCountry($country);

        $baseContext = new BaseSalesChannelContext(
            Context::createDefaultContext(new SalesChannelApiSource($salesChannel->getId())),
            $salesChannel,
            $currency,
            new CustomerGroupEntity(),
            new TaxCollection(),
            new PaymentMethodEntity(),
            new ShippingMethodEntity(),
            new ShippingLocation($country, null, null),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            Generator::createLanguageInfo(),
            MeasurementUnits::createDefaultUnits()
        );

        $baseSalesChannelContextFactory = static::createStub(AbstractBaseSalesChannelContextFactory::class);
        $baseSalesChannelContextFactory->method('create')->willReturn($baseContext);

        $factory = new SalesChannelContextFactory(
            new StaticEntityRepository([new CustomerCollection([$customer])], new CustomerDefinition()),
            static::createStub(EntityRepository::class),
            new StaticEntityRepository([new CustomerAddressCollection([$billingAddress, $shippingAddress])], new CustomerAddressDefinition()),
            static::createStub(EntityRepository::class),
            static::createStub(TaxDetector::class),
            [],
            static::createStub(EventDispatcherInterface::class),
            static::createStub(EntityRepository::class),
            $baseSalesChannelContextFactory,
            new StaticEntityRepository([$orderAddresses], new OrderAddressDefinition()),
        );

        $context = $factory->create(
            Uuid::randomHex(),
            $salesChannel->getId(),
            [SalesChannelContextService::CUSTOMER_ID => $customer->getId(), ...$options],
        );

        return [$context, $shippingAddress];
    }
}
