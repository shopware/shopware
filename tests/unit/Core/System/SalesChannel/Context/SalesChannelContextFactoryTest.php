<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
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
use Shopware\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingCollection;
use Shopware\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingDefinition;
use Shopware\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\BaseSalesChannelContext;
use Shopware\Core\System\SalesChannel\Context\AbstractBaseSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelContextFactory::class)]
class SalesChannelContextFactoryTest extends TestCase
{
    private SalesChannelEntity $salesChannel;

    private PaymentMethodEntity $basePaymentMethod;

    protected function setUp(): void
    {
        $this->salesChannel = new SalesChannelEntity();
        $this->salesChannel->setId(Uuid::randomHex());

        $this->basePaymentMethod = new PaymentMethodEntity();
        $this->basePaymentMethod->setId(Uuid::randomHex());
    }

    public function testCustomerPaymentMethodIsOnlyUsedIfActive(): void
    {
        $salesChannel = $this->salesChannel;
        $basePaymentMethod = $this->basePaymentMethod;

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

        /** @var StaticEntityRepository<PaymentMethodCollection> $paymentMethodRepository */
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

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
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

        /** @var StaticEntityRepository<CustomerAddressCollection> $addressRepository */
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
            $this->createMock(EntityRepository::class),
            $addressRepository,
            $paymentMethodRepository,
            $this->createMock(TaxDetector::class),
            [],
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(EntityRepository::class),
            $baseSalesChannelContextFactory,
        );

        $generatedContext = $factory->create(Uuid::randomHex(), $salesChannel->getId(), $options);
        static::assertSame($generatedContext->getPaymentMethod(), $baseContext->getPaymentMethod());
    }

    public function testCustomerIsNullIfInactive(): void
    {
        $salesChannel = $this->salesChannel;
        $basePaymentMethod = $this->basePaymentMethod;

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

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
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

        /** @var StaticEntityRepository<CustomerAddressCollection> $addressRepository */
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
            $this->createMock(EntityRepository::class),
            $addressRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(TaxDetector::class),
            [],
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(EntityRepository::class),
            $baseSalesChannelContextFactory,
        );

        $generatedContext = $factory->create(Uuid::randomHex(), $salesChannel->getId(), $options);
        static::assertNull($generatedContext->getCustomer());
    }

    public function testCustomerIsSetIfActive(): void
    {
        $salesChannel = $this->salesChannel;
        $basePaymentMethod = $this->basePaymentMethod;

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

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
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

        /** @var StaticEntityRepository<CustomerAddressCollection> $addressRepository */
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
            $this->createMock(EntityRepository::class),
            $addressRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(TaxDetector::class),
            [],
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(EntityRepository::class),
            $baseSalesChannelContextFactory,
        );

        $generatedContext = $factory->create(Uuid::randomHex(), $salesChannel->getId(), $options);
        static::assertSame($customer, $generatedContext->getCustomer());
    }

    public function testPaymentMethodFromOptionsTakesPrecedenceOverCustomerLast(): void
    {
        $salesChannel = $this->salesChannel;
        $basePaymentMethod = $this->basePaymentMethod;

        $country = $this->makeCountry();
        $customer = $this->makeCustomer(lastPaymentMethodId: Uuid::randomHex());
        $addresses = $this->makeAddresses($customer, $country);
        $baseContext = $this->makeBaseContext($salesChannel, $country, $basePaymentMethod);

        $paymentMethodRepository = $this->expectsNoSearch();

        $options = [
            SalesChannelContextService::CUSTOMER_ID => $customer->getId(),
            SalesChannelContextService::PAYMENT_METHOD_ID => Uuid::randomHex(),
        ];

        $generatedContext = $this->makeFactory(
            customer: $customer,
            addresses: $addresses,
            paymentMethodRepository: $paymentMethodRepository,
            baseContext: $baseContext,
        )->create(Uuid::randomHex(), $salesChannel->getId(), $options);

        static::assertSame($basePaymentMethod, $generatedContext->getPaymentMethod());
    }

    public function testCustomerLastPaymentMethodMatchingContextSkipsLookup(): void
    {
        $salesChannel = $this->salesChannel;
        $basePaymentMethod = $this->basePaymentMethod;

        $country = $this->makeCountry();
        $customer = $this->makeCustomer(lastPaymentMethodId: $basePaymentMethod->getId());
        $addresses = $this->makeAddresses($customer, $country);
        $baseContext = $this->makeBaseContext($salesChannel, $country, $basePaymentMethod);

        $paymentMethodRepository = $this->expectsNoSearch();

        $options = [SalesChannelContextService::CUSTOMER_ID => $customer->getId()];

        $generatedContext = $this->makeFactory(
            customer: $customer,
            addresses: $addresses,
            paymentMethodRepository: $paymentMethodRepository,
            baseContext: $baseContext,
        )->create(Uuid::randomHex(), $salesChannel->getId(), $options);

        static::assertSame($basePaymentMethod, $generatedContext->getPaymentMethod());
    }

    public function testCustomerLastPaymentMethodIsResolvedFromRepository(): void
    {
        $salesChannel = $this->salesChannel;
        $basePaymentMethod = $this->basePaymentMethod;

        $resolvedPaymentMethod = new PaymentMethodEntity();
        $resolvedPaymentMethod->setId(Uuid::randomHex());

        $country = $this->makeCountry();
        $customer = $this->makeCustomer(lastPaymentMethodId: $resolvedPaymentMethod->getId());
        $addresses = $this->makeAddresses($customer, $country);
        $baseContext = $this->makeBaseContext($salesChannel, $country, $basePaymentMethod);

        /** @var StaticEntityRepository<PaymentMethodCollection> $paymentMethodRepository */
        $paymentMethodRepository = new StaticEntityRepository(
            [
                static fn (Criteria $criteria, Context $context) => new EntitySearchResult(
                    PaymentMethodDefinition::ENTITY_NAME,
                    1,
                    new PaymentMethodCollection([$resolvedPaymentMethod]),
                    null,
                    $criteria,
                    $context,
                ),
            ],
            new PaymentMethodDefinition(),
        );

        $options = [SalesChannelContextService::CUSTOMER_ID => $customer->getId()];

        $generatedContext = $this->makeFactory(
            customer: $customer,
            addresses: $addresses,
            paymentMethodRepository: $paymentMethodRepository,
            baseContext: $baseContext,
        )->create(Uuid::randomHex(), $salesChannel->getId(), $options);

        static::assertSame($resolvedPaymentMethod, $generatedContext->getPaymentMethod());
    }

    #[TestDox('cash rounding (countries differ): $_dataName')]
    #[DataProvider('cashRoundingProvider')]
    public function testCashRoundingResolutionWhenCountriesDiffer(
        ?CurrencyCountryRoundingEntity $countryConfig,
        CashRoundingConfig $currencyItem,
        CashRoundingConfig $currencyTotal,
        CashRoundingConfig $expectedItem,
        CashRoundingConfig $expectedTotal,
    ): void {
        $salesChannel = $this->salesChannel;
        $basePaymentMethod = $this->basePaymentMethod;

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setFactor(1);
        $currency->setItemRounding($currencyItem);
        $currency->setTotalRounding($currencyTotal);

        $baseCountry = $this->makeCountry();
        $customerCountry = $this->makeCountry();
        $baseContext = $this->makeBaseContext($salesChannel, $baseCountry, $basePaymentMethod, currency: $currency);

        $customer = $this->makeCustomer(lastPaymentMethodId: null);
        $addresses = $this->makeAddresses($customer, $customerCountry);

        $collection = $countryConfig === null
            ? new CurrencyCountryRoundingCollection()
            : new CurrencyCountryRoundingCollection([$countryConfig]);

        /** @var StaticEntityRepository<CurrencyCountryRoundingCollection> $currencyCountryRepository */
        $currencyCountryRepository = new StaticEntityRepository(
            [$collection],
            new CurrencyCountryRoundingDefinition(),
        );

        $options = [SalesChannelContextService::CUSTOMER_ID => $customer->getId()];

        $generatedContext = $this->makeFactory(
            customer: $customer,
            addresses: $addresses,
            currencyCountryRepository: $currencyCountryRepository,
            baseContext: $baseContext,
        )->create(Uuid::randomHex(), $salesChannel->getId(), $options);

        static::assertSame($expectedItem, $generatedContext->getItemRounding());
        static::assertSame($expectedTotal, $generatedContext->getTotalRounding());
    }

    /**
     * @return \Generator<string, array{0: ?CurrencyCountryRoundingEntity, 1: CashRoundingConfig, 2: CashRoundingConfig, 3: CashRoundingConfig, 4: CashRoundingConfig}>
     */
    public static function cashRoundingProvider(): \Generator
    {
        $currencyItem = new CashRoundingConfig(3, 0.001, true);
        $currencyTotal = new CashRoundingConfig(3, 0.001, false);

        $configItem = new CashRoundingConfig(2, 0.05, true);
        $configTotal = new CashRoundingConfig(2, 0.05, false);
        $countryConfig = new CurrencyCountryRoundingEntity();
        $countryConfig->setUniqueIdentifier(Uuid::randomHex());
        $countryConfig->setItemRounding($configItem);
        $countryConfig->setTotalRounding($configTotal);

        yield 'country config wins when present' => [$countryConfig, $currencyItem, $currencyTotal, $configItem, $configTotal];

        yield 'falls back to currency default when no country config' => [null, $currencyItem, $currencyTotal, $currencyItem, $currencyTotal];
    }

    private function makeCountry(): CountryEntity
    {
        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());

        return $country;
    }

    private function makeCustomer(?string $lastPaymentMethodId): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setActive(true);
        $customer->setLastPaymentMethodId($lastPaymentMethodId);
        $customer->setDefaultBillingAddressId(Uuid::randomHex());
        $customer->setDefaultShippingAddressId(Uuid::randomHex());
        $customer->setGroupId(Uuid::randomHex());

        return $customer;
    }

    private function makeAddresses(CustomerEntity $customer, CountryEntity $shippingCountry): CustomerAddressCollection
    {
        $billing = new CustomerAddressEntity();
        $billing->setId($customer->getDefaultBillingAddressId() ?? Uuid::randomHex());

        $shipping = new CustomerAddressEntity();
        $shipping->setId($customer->getDefaultShippingAddressId() ?? Uuid::randomHex());
        $shipping->setCountry($shippingCountry);

        return new CustomerAddressCollection([$billing, $shipping]);
    }

    private function makeBaseContext(
        SalesChannelEntity $salesChannel,
        ?CountryEntity $shippingCountry,
        PaymentMethodEntity $paymentMethod,
        ?CurrencyEntity $currency = null,
    ): BaseSalesChannelContext {
        if ($currency === null) {
            $currency = new CurrencyEntity();
            $currency->setId(Uuid::randomHex());
            $currency->setFactor(1);
        }

        return new BaseSalesChannelContext(
            Context::createDefaultContext(new SalesChannelApiSource($salesChannel->getId())),
            $salesChannel,
            $currency,
            new CustomerGroupEntity(),
            new TaxCollection(),
            $paymentMethod,
            new ShippingMethodEntity(),
            new ShippingLocation($shippingCountry ?? $this->makeCountry(), null, null),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            Generator::createLanguageInfo(),
            MeasurementUnits::createDefaultUnits(),
        );
    }

    /**
     * @return StaticEntityRepository<PaymentMethodCollection>
     */
    private function expectsNoSearch(): StaticEntityRepository
    {
        return new StaticEntityRepository([], new PaymentMethodDefinition());
    }

    private function makeFactory(
        CustomerEntity $customer,
        CustomerAddressCollection $addresses,
        BaseSalesChannelContext $baseContext,
        ?EntityRepository $paymentMethodRepository = null,
        ?EntityRepository $currencyCountryRepository = null,
    ): SalesChannelContextFactory {
        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository(
            [
                static fn (Criteria $criteria, Context $context) => new EntitySearchResult(
                    CustomerDefinition::ENTITY_NAME,
                    1,
                    new CustomerCollection([$customer]),
                    null,
                    $criteria,
                    $context,
                ),
            ],
            new CustomerDefinition(),
        );

        /** @var StaticEntityRepository<CustomerAddressCollection> $addressRepository */
        $addressRepository = new StaticEntityRepository(
            [
                static fn (Criteria $criteria, Context $context) => new EntitySearchResult(
                    CustomerAddressDefinition::ENTITY_NAME,
                    $addresses->count(),
                    $addresses,
                    null,
                    $criteria,
                    $context,
                ),
            ],
            new CustomerAddressDefinition(),
        );

        $baseSalesChannelContextFactory = $this->createMock(AbstractBaseSalesChannelContextFactory::class);
        $baseSalesChannelContextFactory
            ->expects($this->once())
            ->method('create')
            ->willReturn($baseContext);

        return new SalesChannelContextFactory(
            $customerRepository,
            $this->createMock(EntityRepository::class),
            $addressRepository,
            $paymentMethodRepository ?? $this->createMock(EntityRepository::class),
            $this->createMock(TaxDetector::class),
            [],
            $this->createMock(EventDispatcherInterface::class),
            $currencyCountryRepository ?? $this->createMock(EntityRepository::class),
            $baseSalesChannelContextFactory,
        );
    }
}
