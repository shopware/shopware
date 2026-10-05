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
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
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
use Shopware\Core\System\SalesChannel\BaseContext;
use Shopware\Core\System\SalesChannel\Context\AbstractBaseContextFactory;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelContextFactory::class)]
class SalesChannelContextFactoryTest extends TestCase
{
    private const CUSTOMER_BILLING_ADDRESS_ID = 'customer-billing-address-id';

    private const CUSTOMER_SHIPPING_ADDRESS_ID = 'customer-shipping-address-id';

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

        $baseContext = new BaseContext(
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
            new LanguageInfo('English', 'en-GB'),
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

        $baseContextFactory = $this->createMock(AbstractBaseContextFactory::class);
        $baseContextFactory
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
            $baseContextFactory,
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

        $baseContext = new BaseContext(
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
            new LanguageInfo('English', 'en-GB'),
        );

        $options = [
            SalesChannelContextService::CUSTOMER_ID => $customer->getId(),
        ];

        $baseSalesChannelContextFactory = $this->createMock(AbstractBaseContextFactory::class);
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

        $baseContext = new BaseContext(
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
            new LanguageInfo('English', 'en-GB'),
        );

        $options = [
            SalesChannelContextService::CUSTOMER_ID => $customer->getId(),
        ];

        $baseSalesChannelContextFactory = $this->createMock(AbstractBaseContextFactory::class);
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

    public function testInjectedShippingAddressBecomesTheActiveShippingAddress(): void
    {
        $injectedAddress = $this->createInjectedAddress();

        $context = $this->createContext([SalesChannelContextService::SHIPPING_ADDRESS => $injectedAddress]);

        $customer = $context->getCustomer();
        static::assertNotNull($customer);
        static::assertSame($injectedAddress, $customer->getActiveShippingAddress());
        static::assertSame($injectedAddress, $context->getShippingLocation()->getAddress());
        static::assertSame($injectedAddress->getCountry(), $context->getShippingLocation()->getCountry());
        static::assertSame(self::CUSTOMER_BILLING_ADDRESS_ID, $customer->getActiveBillingAddress()?->getId());
    }

    public function testInjectedBillingAddressBecomesTheActiveBillingAddress(): void
    {
        $injectedAddress = $this->createInjectedAddress();

        $context = $this->createContext([SalesChannelContextService::BILLING_ADDRESS => $injectedAddress]);

        $customer = $context->getCustomer();
        static::assertNotNull($customer);
        static::assertSame($injectedAddress, $customer->getActiveBillingAddress());
        static::assertSame(self::CUSTOMER_SHIPPING_ADDRESS_ID, $customer->getActiveShippingAddress()?->getId());
    }

    #[DataProvider('addressOptionProvider')]
    public function testExistingAddressIdTakesPrecedenceOverTheInjectedAddress(string $idOption, string $addressOption, string $customerAddressId): void
    {
        $context = $this->createContext([
            $idOption => $customerAddressId,
            $addressOption => $this->createInjectedAddress(),
        ]);

        $customer = $context->getCustomer();
        static::assertNotNull($customer);

        $activeAddress = $addressOption === SalesChannelContextService::BILLING_ADDRESS
            ? $customer->getActiveBillingAddress()
            : $customer->getActiveShippingAddress();

        static::assertSame($customerAddressId, $activeAddress?->getId());
    }

    #[DataProvider('unknownAddressIdProvider')]
    public function testUnknownAddressIdFallsBackToTheInjectedAddress(string $idOption, string $addressOption): void
    {
        $injectedAddress = $this->createInjectedAddress();

        $context = $this->createContext([
            $idOption => Uuid::randomHex(),
            $addressOption => $injectedAddress,
        ]);

        $customer = $context->getCustomer();
        static::assertNotNull($customer);

        $activeAddress = $addressOption === SalesChannelContextService::BILLING_ADDRESS
            ? $customer->getActiveBillingAddress()
            : $customer->getActiveShippingAddress();

        static::assertSame($injectedAddress, $activeAddress);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function addressOptionProvider(): iterable
    {
        yield 'billing address' => [
            SalesChannelContextService::BILLING_ADDRESS_ID,
            SalesChannelContextService::BILLING_ADDRESS,
            self::CUSTOMER_BILLING_ADDRESS_ID,
        ];

        yield 'shipping address' => [
            SalesChannelContextService::SHIPPING_ADDRESS_ID,
            SalesChannelContextService::SHIPPING_ADDRESS,
            self::CUSTOMER_SHIPPING_ADDRESS_ID,
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unknownAddressIdProvider(): iterable
    {
        yield 'billing address' => [SalesChannelContextService::BILLING_ADDRESS_ID, SalesChannelContextService::BILLING_ADDRESS];
        yield 'shipping address' => [SalesChannelContextService::SHIPPING_ADDRESS_ID, SalesChannelContextService::SHIPPING_ADDRESS];
    }

    public function testInjectedShippingAddressDefinesTheShippingLocationWithoutCustomer(): void
    {
        $injectedAddress = $this->createInjectedAddress();

        $context = $this->createContext([SalesChannelContextService::SHIPPING_ADDRESS => $injectedAddress], withCustomer: false);

        static::assertNull($context->getCustomer());
        static::assertSame($injectedAddress, $context->getShippingLocation()->getAddress());
        static::assertSame($injectedAddress->getCountry(), $context->getShippingLocation()->getCountry());
    }

    private function createInjectedAddress(): CustomerAddressEntity
    {
        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());

        $address = new CustomerAddressEntity();
        $address->setId(Uuid::randomHex());
        $address->setZipcode('48624');
        $address->setCountryId($country->getId());
        $address->setCountry($country);

        return $address;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createContext(array $options, bool $withCustomer = true): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setActive(true);
        $customer->setDefaultBillingAddressId(self::CUSTOMER_BILLING_ADDRESS_ID);
        $customer->setDefaultShippingAddressId(self::CUSTOMER_SHIPPING_ADDRESS_ID);
        $customer->setGroupId(Uuid::randomHex());

        $salesChannelCountry = new CountryEntity();
        $salesChannelCountry->setId(Uuid::randomHex());

        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setId(self::CUSTOMER_BILLING_ADDRESS_ID);
        $billingAddress->setCountry($salesChannelCountry);

        $shippingAddress = new CustomerAddressEntity();
        $shippingAddress->setId(self::CUSTOMER_SHIPPING_ADDRESS_ID);
        $shippingAddress->setCountry($salesChannelCountry);

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setFactor(1);
        $currency->setItemRounding(new CashRoundingConfig(2, 0.01, true));
        $currency->setTotalRounding(new CashRoundingConfig(2, 0.01, true));

        $baseContext = new BaseContext(
            Context::createDefaultContext(new SalesChannelApiSource($salesChannel->getId())),
            $salesChannel,
            $currency,
            new CustomerGroupEntity(),
            new TaxCollection(),
            new PaymentMethodEntity(),
            new ShippingMethodEntity(),
            new ShippingLocation($salesChannelCountry, null, null),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            new LanguageInfo('English', 'en-GB'),
        );

        $baseContextFactory = static::createStub(AbstractBaseContextFactory::class);
        $baseContextFactory->method('create')->willReturn($baseContext);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository([new CustomerCollection([$customer])], new CustomerDefinition());
        /** @var StaticEntityRepository<CustomerAddressCollection> $addressRepository */
        $addressRepository = new StaticEntityRepository([new CustomerAddressCollection([$billingAddress, $shippingAddress])], new CustomerAddressDefinition());

        $factory = new SalesChannelContextFactory(
            $customerRepository,
            static::createStub(EntityRepository::class),
            $addressRepository,
            static::createStub(EntityRepository::class),
            static::createStub(TaxDetector::class),
            [],
            static::createStub(EventDispatcherInterface::class),
            static::createStub(EntityRepository::class),
            $baseContextFactory,
        );

        if ($withCustomer) {
            $options[SalesChannelContextService::CUSTOMER_ID] = $customer->getId();
        }

        return $factory->create(Uuid::randomHex(), $salesChannel->getId(), $options);
    }
}
