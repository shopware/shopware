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
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
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

    private CustomerEntity $customer;

    protected function setUp(): void
    {
        $this->salesChannel = new SalesChannelEntity();
        $this->salesChannel->setId(Uuid::randomHex());

        $this->basePaymentMethod = new PaymentMethodEntity();
        $this->basePaymentMethod->setId(Uuid::randomHex());

        $this->customer = $this->makeCustomer(lastPaymentMethodId: Uuid::randomHex());
    }

    public function testCustomerPaymentMethodIsOnlyUsedIfActive(): void
    {
        $customer = $this->customer;
        $country = $this->makeCountry();
        $addresses = $this->makeAddresses($customer, $country);
        $baseContext = $this->makeBaseContext($this->salesChannel, $country, $this->basePaymentMethod);

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

        $options = [SalesChannelContextService::CUSTOMER_ID => $customer->getId()];

        $generatedContext = $this->makeFactory(
            customer: $customer,
            addresses: $addresses,
            baseContext: $baseContext,
            paymentMethodRepository: $paymentMethodRepository,
        )->create(Uuid::randomHex(), $this->salesChannel->getId(), $options);

        static::assertSame($generatedContext->getPaymentMethod(), $baseContext->getPaymentMethod());
    }

    public function testCustomerIsNullIfInactive(): void
    {
        $customer = $this->customer;
        $customer->setActive(false);
        $country = $this->makeCountry();
        $addresses = $this->makeAddresses($customer, $country);
        $baseContext = $this->makeBaseContext($this->salesChannel, $country, $this->basePaymentMethod);

        $options = [SalesChannelContextService::CUSTOMER_ID => $customer->getId()];

        $generatedContext = $this->makeFactory(
            customer: $customer,
            addresses: $addresses,
            baseContext: $baseContext,
        )->create(Uuid::randomHex(), $this->salesChannel->getId(), $options);

        static::assertNull($generatedContext->getCustomer());
    }

    public function testCustomerIsSetIfActive(): void
    {
        $customer = $this->customer;
        $country = $this->makeCountry();
        $addresses = $this->makeAddresses($customer, $country);
        $baseContext = $this->makeBaseContext($this->salesChannel, $country, $this->basePaymentMethod);

        $options = [SalesChannelContextService::CUSTOMER_ID => $customer->getId()];

        $generatedContext = $this->makeFactory(
            customer: $customer,
            addresses: $addresses,
            baseContext: $baseContext,
        )->create(Uuid::randomHex(), $this->salesChannel->getId(), $options);

        static::assertSame($customer, $generatedContext->getCustomer());
    }

    #[TestDox('payment method resolution: $_dataName')]
    #[DataProvider('paymentMethodProvider')]
    public function testPaymentMethodResolution(
        string $lastIdMode,
        bool $optionsHasPaymentMethodId,
        bool $repoReturnsResolved,
        string $expects,
    ): void {
        $resolvedPaymentMethod = new PaymentMethodEntity();
        $resolvedPaymentMethod->setId(Uuid::randomHex());

        $lastPaymentMethodId = match ($lastIdMode) {
            'random' => Uuid::randomHex(),
            'matchesContext' => $this->basePaymentMethod->getId(),
            'matchesResolved' => $resolvedPaymentMethod->getId(),
            default => throw new \LogicException("unknown lastIdMode: {$lastIdMode}"),
        };

        $country = $this->makeCountry();
        $customer = $this->customer;
        $customer->setLastPaymentMethodId($lastPaymentMethodId);
        $addresses = $this->makeAddresses($customer, $country);
        $baseContext = $this->makeBaseContext($this->salesChannel, $country, $this->basePaymentMethod);

        $paymentMethodRepository = $repoReturnsResolved
            ? $this->repoReturning(new PaymentMethodCollection([$resolvedPaymentMethod]), new PaymentMethodDefinition())
            : $this->expectsNoSearch();

        $options = [SalesChannelContextService::CUSTOMER_ID => $customer->getId()];
        if ($optionsHasPaymentMethodId) {
            $options[SalesChannelContextService::PAYMENT_METHOD_ID] = Uuid::randomHex();
        }

        $generatedContext = $this->makeFactory(
            customer: $customer,
            addresses: $addresses,
            paymentMethodRepository: $paymentMethodRepository,
            baseContext: $baseContext,
        )->create(Uuid::randomHex(), $this->salesChannel->getId(), $options);

        $expected = $expects === 'resolved' ? $resolvedPaymentMethod : $this->basePaymentMethod;

        static::assertSame($expected, $generatedContext->getPaymentMethod());
    }

    /**
     * @return \Generator<string, array{0: string, 1: bool, 2: bool, 3: string}>
     */
    public static function paymentMethodProvider(): \Generator
    {
        yield 'option PAYMENT_METHOD_ID overrides customer.last' => ['random', true, false, 'context'];

        yield 'customer.last == context payment skips lookup' => ['matchesContext', false, false, 'context'];

        yield 'customer.last resolved from repository' => ['matchesResolved', false, true, 'resolved'];
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

        $customer = $this->customer;
        $customer->setLastPaymentMethodId(null);
        $addresses = $this->makeAddresses($customer, $customerCountry);

        $collection = $countryConfig === null
            ? new CurrencyCountryRoundingCollection()
            : new CurrencyCountryRoundingCollection([$countryConfig]);

        $currencyCountryRepository = $this->repoReturning($collection, new CurrencyCountryRoundingDefinition());

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

    /**
     * @template TCollection of EntityCollection<\Shopware\Core\Framework\DataAbstractionLayer\Entity>
     *
     * @param TCollection $collection
     *
     * @return StaticEntityRepository<TCollection>
     */
    private function repoReturning(EntityCollection $collection, EntityDefinition $definition): StaticEntityRepository
    {
        return new StaticEntityRepository(
            [
                static fn (Criteria $criteria, Context $context) => new EntitySearchResult(
                    $definition->getEntityName(),
                    $collection->count(),
                    $collection,
                    null,
                    $criteria,
                    $context,
                ),
            ],
            $definition,
        );
    }

    private function makeFactory(
        CustomerEntity $customer,
        CustomerAddressCollection $addresses,
        BaseSalesChannelContext $baseContext,
        ?EntityRepository $paymentMethodRepository = null,
        ?EntityRepository $currencyCountryRepository = null,
    ): SalesChannelContextFactory {
        $customerRepository = $this->repoReturning(new CustomerCollection([$customer]), new CustomerDefinition());
        $addressRepository = $this->repoReturning($addresses, new CustomerAddressDefinition());

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
