<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Tax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Extension\ResolveDispatchCountryExtension;
use Shopware\Core\Checkout\Cart\Extension\ResolveVatIdsExtension;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\IntraCommunityTaxResolver;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(IntraCommunityTaxResolver::class)]
class IntraCommunityTaxResolverTest extends TestCase
{
    private const DE_ID = '11111111111111111111111111111111';

    private const FR_ID = '22222222222222222222222222222222';

    private const NL_ID = '33333333333333333333333333333333';

    private const CH_ID = '44444444444444444444444444444444';

    private const CHANNEL_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const SELLER_VAT = 'DE123456789';

    private const FOREIGN_VAT = 'NL123456789B01';

    private const ORDER_ID = 'dddddddddddddddddddddddddddddddd';

    private const ORDER_VERSION_ID = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    private CountryCollection $countries;

    private StaticSystemConfigService $systemConfig;

    private IntraCommunityTaxResolver $resolver;

    private EventDispatcher $dispatcher;

    /**
     * The product line items saved on the order, physical unless a test says otherwise
     */
    private OrderLineItemCollection $products;

    /**
     * @var StaticEntityRepository<OrderLineItemCollection>
     */
    private StaticEntityRepository $orderLineItemRepository;

    /**
     * @var list<ResolveDispatchCountryExtension|ResolveVatIdsExtension>
     */
    private array $published = [];

    protected function setUp(): void
    {
        $this->countries = new CountryCollection();
        foreach ([
            ['id' => self::DE_ID, 'iso' => 'DE', 'vatIdPattern' => 'DE\\d{9}', 'isEu' => true],
            ['id' => self::FR_ID, 'iso' => 'FR', 'vatIdPattern' => 'FR\\d{11}', 'isEu' => true],
            ['id' => self::NL_ID, 'iso' => 'NL', 'vatIdPattern' => 'NL\\d{9}B\\d{2}', 'isEu' => true],
            ['id' => self::CH_ID, 'iso' => 'CH', 'vatIdPattern' => 'CHE\\d{9}', 'isEu' => false],
        ] as $data) {
            $this->countries->add((new CountryEntity())->assign([
                ...$data,
                'checkVatIdPattern' => true,
                'customerTax' => new TaxFreeConfig(false),
                'companyTax' => new TaxFreeConfig(true),
            ]));
        }

        $euCountries = $this->countries->filter(static fn (CountryEntity $country) => $country->getIsEu());
        $this->systemConfig = new StaticSystemConfigService(['core.basicInformation.sellerCountryId' => self::DE_ID]);
        $this->dispatcher = new EventDispatcher();
        $this->products = new OrderLineItemCollection([self::product(ProductDefinition::TYPE_PHYSICAL)]);
        $this->orderLineItemRepository = StaticEntityRepository::of(OrderLineItemCollection::class, [fn (): OrderLineItemCollection => $this->products]);
        $this->resolver = new IntraCommunityTaxResolver(
            new VatIdPatternProvider(new StaticEntityRepository([$euCountries])),
            $this->systemConfig,
            StaticEntityRepository::of(CountryCollection::class, [[self::DE_ID]]),
            new ExtensionDispatcher($this->dispatcher),
            $this->orderLineItemRepository,
        );
    }

    /**
     * @param list<string>|null $vatIds
     */
    #[DataProvider('goodsMatrixProvider')]
    public function testGoodsMatrix(?array $vatIds, string $deliveryCountryId, bool $exempt, string $taxCountryId): void
    {
        $country = $this->country($deliveryCountryId);

        static::assertSame($exempt, $this->resolver->isExempt(self::business($vatIds), $country, self::CHANNEL_ID));
        static::assertSame($taxCountryId, $this->resolver->getTaxCountryId(self::business($vatIds), $country, self::CHANNEL_ID));
    }

    /**
     * @return iterable<string, array{list<string>|null, string, bool, string}>
     */
    public static function goodsMatrixProvider(): iterable
    {
        yield 'foreign EU VAT and export are exempt' => [[self::FOREIGN_VAT], self::CH_ID, true, self::CH_ID];
        yield 'foreign EU VAT and cross-border EU delivery are exempt' => [[self::FOREIGN_VAT], self::FR_ID, true, self::FR_ID];
        yield 'foreign EU VAT and domestic delivery use seller VAT' => [[self::FOREIGN_VAT], self::DE_ID, false, self::DE_ID];
        yield 'seller VAT and export are exempt' => [[self::SELLER_VAT], self::CH_ID, true, self::CH_ID];
        yield 'seller VAT and cross-border EU delivery use seller VAT' => [[self::SELLER_VAT], self::FR_ID, false, self::DE_ID];
        yield 'seller VAT and domestic delivery use seller VAT' => [[self::SELLER_VAT], self::DE_ID, false, self::DE_ID];
        yield 'no VAT and export are exempt' => [null, self::CH_ID, true, self::CH_ID];
        yield 'no VAT and cross-border EU delivery use destination VAT' => [null, self::FR_ID, false, self::FR_ID];
        yield 'no VAT and domestic delivery use seller VAT' => [null, self::DE_ID, false, self::DE_ID];
    }

    /**
     * @param list<string> $vatIds
     */
    #[DataProvider('vatIdsProvider')]
    public function testVatEligibilityAndTaxCountrySelection(array $vatIds, bool $checkVatIdPattern, bool $exempt, string $taxCountryId): void
    {
        $country = $this->country(self::FR_ID);
        $country->setCheckVatIdPattern($checkVatIdPattern);

        static::assertSame($exempt, $this->resolver->isExempt(self::business($vatIds), $country, self::CHANNEL_ID));
        static::assertSame($taxCountryId, $this->resolver->getTaxCountryId(self::business($vatIds), $country, self::CHANNEL_ID));
    }

    /**
     * @return iterable<string, array{list<string>, bool, bool, string}>
     */
    public static function vatIdsProvider(): iterable
    {
        yield 'empty VAT list remains taxable at destination' => [[], true, false, self::FR_ID];
        yield 'empty entries remain taxable at destination' => [[''], true, false, self::FR_ID];
        yield 'invalid VAT remains taxable at destination' => [['INVALID'], true, false, self::FR_ID];
        yield 'VAT matching the delivery country is exempt' => [['FR12345678901'], true, true, self::FR_ID];
        yield 'multiple foreign VAT IDs remain exempt' => [[self::FOREIGN_VAT, 'FR12345678901'], true, true, self::FR_ID];
        yield 'invalid seller VAT does not select origin' => [['DEINVALID', self::FOREIGN_VAT], true, false, self::FR_ID];
        yield 'seller VAT next to a foreign VAT selects origin' => [[self::FOREIGN_VAT, self::SELLER_VAT], true, false, self::DE_ID];
        yield 'empty entry next to seller VAT is ignored' => [['', self::SELLER_VAT], true, false, self::DE_ID];
        yield 'invalid VAT next to seller VAT selects origin' => [['INVALID', self::SELLER_VAT], true, false, self::DE_ID];
        yield 'unchecked delivery country still needs a VAT ID' => [[], false, false, self::FR_ID];
        yield 'unchecked delivery country exempts any VAT' => [['INVALID'], false, true, self::FR_ID];
        yield 'unchecked delivery country keeps the seller VAT exclusion' => [[self::FOREIGN_VAT, self::SELLER_VAT], false, false, self::DE_ID];
        yield 'seller VAT behind more IDs than member states selects origin' => [[self::FOREIGN_VAT, self::FOREIGN_VAT, self::FOREIGN_VAT, self::SELLER_VAT], false, false, self::DE_ID];
    }

    #[DataProvider('nonBusinessCustomerProvider')]
    public function testOriginVatRequiresABusinessAccount(?CustomerEntity $customer): void
    {
        $country = $this->country(self::FR_ID);

        static::assertFalse($this->resolver->isExempt($customer, $country, self::CHANNEL_ID));
        static::assertSame(self::FR_ID, $this->resolver->getTaxCountryId($customer, $country, self::CHANNEL_ID));
    }

    /**
     * @return iterable<string, array{CustomerEntity|null}>
     */
    public static function nonBusinessCustomerProvider(): iterable
    {
        $private = new CustomerEntity();
        $private->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);
        $private->setVatIds([self::SELLER_VAT]);

        yield 'private account' => [$private];
        yield 'missing customer' => [null];
    }

    #[DataProvider('billingCountryProvider')]
    public function testDigitalProductsReadTheBillingCountryOnlyWithinTheOptIn(?CustomerEntity $customer, string $billingCountryId, bool $companyTaxFree, string $sellerCountryId, bool $expected): void
    {
        $this->systemConfig->set('core.basicInformation.sellerCountryId', $sellerCountryId);
        $country = $this->country($billingCountryId);
        $country->setCompanyTax(new TaxFreeConfig($companyTaxFree));

        static::assertSame($expected, $this->resolver->isTaxedByBillingCountry($customer, $country, self::CHANNEL_ID));
    }

    /**
     * @return iterable<string, array{CustomerEntity|null, string, bool, string, bool}>
     */
    public static function billingCountryProvider(): iterable
    {
        $private = new CustomerEntity();
        $private->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);

        yield 'business customer billed in another member state with Tax-free (B2B)' => [self::business(null), self::FR_ID, true, self::DE_ID, true];
        yield 'business customer billed in the seller country with Tax-free (B2B)' => [self::business(null), self::DE_ID, true, self::DE_ID, true];
        yield 'business customer billed in the seller country without Tax-free (B2B)' => [self::business(null), self::DE_ID, false, self::DE_ID, true];
        yield 'billing country without Tax-free (B2B)' => [self::business(null), self::FR_ID, false, self::DE_ID, false];
        yield 'billing country outside the EU' => [self::business(null), self::CH_ID, true, self::DE_ID, false];
        yield 'seller outside the EU' => [self::business(null), self::FR_ID, true, self::CH_ID, false];
        yield 'private customer' => [$private, self::FR_ID, true, self::DE_ID, false];
        yield 'missing customer, for example of an order whose customer was deleted' => [null, self::FR_ID, true, self::DE_ID, false];
    }

    public function testDisabledCompanyTaxKeepsDestinationRules(): void
    {
        $country = $this->country(self::FR_ID);
        $country->setCompanyTax(new TaxFreeConfig(false));

        static::assertFalse($this->resolver->isExempt(self::business([self::FOREIGN_VAT]), $country, self::CHANNEL_ID));
        static::assertSame(self::FR_ID, $this->resolver->getTaxCountryId(self::business([self::SELLER_VAT]), $country, self::CHANNEL_ID));
    }

    public function testConfiguredSellerOverridesTheSalesChannelDefault(): void
    {
        $this->systemConfig->set('core.basicInformation.sellerCountryId', self::NL_ID);
        $country = $this->country(self::FR_ID);

        static::assertFalse($this->resolver->isExempt(self::business([self::FOREIGN_VAT]), $country, self::CHANNEL_ID));
        static::assertSame(self::NL_ID, $this->resolver->getTaxCountryId(self::business([self::FOREIGN_VAT]), $country, self::CHANNEL_ID));
    }

    public function testMissingSellerConfigurationUsesTheSalesChannelDefault(): void
    {
        $this->systemConfig->set('core.basicInformation.sellerCountryId', '');
        $country = $this->country(self::FR_ID);

        static::assertSame(self::DE_ID, $this->resolver->getTaxCountryId(self::business([self::SELLER_VAT]), $country, self::CHANNEL_ID));
        static::assertFalse($this->resolver->isExempt(self::business([self::SELLER_VAT]), $country, self::CHANNEL_ID));
    }

    public function testAnUppercaseSellerCountryIdStillNamesTheSellersMemberState(): void
    {
        $this->systemConfig->set('core.basicInformation.sellerCountryId', strtoupper(self::DE_ID));
        $country = $this->country(self::FR_ID);

        static::assertSame(self::DE_ID, $this->resolver->getTaxCountryId(self::business([self::SELLER_VAT]), $country, self::CHANNEL_ID));
        static::assertTrue($this->resolver->isExempt(self::business([self::FOREIGN_VAT]), $country, self::CHANNEL_ID));
    }

    public function testAnEmptySalesChannelHasNoSellerState(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->never())->method('getString');
        $resolver = new IntraCommunityTaxResolver(
            new VatIdPatternProvider(new StaticEntityRepository([$this->countries->filter(static fn (CountryEntity $country) => $country->getIsEu())])),
            $systemConfig,
            StaticEntityRepository::of(CountryCollection::class),
            new ExtensionDispatcher(new EventDispatcher()),
            StaticEntityRepository::of(OrderLineItemCollection::class),
        );
        $country = $this->country(self::FR_ID);

        static::assertFalse($resolver->isExempt(self::business([self::FOREIGN_VAT]), $country, ''));
        static::assertSame(self::FR_ID, $resolver->getTaxCountryId(self::business([self::SELLER_VAT]), $country, ''));
    }

    public function testTheSalesChannelsDefaultCountryIsReadOncePerSalesChannelUntilReset(): void
    {
        $this->systemConfig->set('core.basicInformation.sellerCountryId', '');
        $countryRepository = StaticEntityRepository::of(CountryCollection::class, [[self::DE_ID], [self::DE_ID]]);
        $resolver = new IntraCommunityTaxResolver(
            new VatIdPatternProvider(new StaticEntityRepository([$this->countries->filter(static fn (CountryEntity $country) => $country->getIsEu())])),
            $this->systemConfig,
            $countryRepository,
            new ExtensionDispatcher(new EventDispatcher()),
            StaticEntityRepository::of(OrderLineItemCollection::class),
        );
        $country = $this->country(self::FR_ID);

        // The context factory and the cart ask several times per request
        $resolver->getTaxCountryId(self::business([self::SELLER_VAT]), $country, self::CHANNEL_ID);
        $resolver->isExempt(self::business([self::SELLER_VAT]), $country, self::CHANNEL_ID);
        static::assertCount(1, $countryRepository->searchedCriteria);

        $resolver->reset();
        $resolver->isExempt(self::business([self::SELLER_VAT]), $country, self::CHANNEL_ID);
        static::assertCount(2, $countryRepository->searchedCriteria);
    }

    public function testUnknownSellerDoesNotGrantAnIntraCommunityExemption(): void
    {
        $this->systemConfig->set('core.basicInformation.sellerCountryId', 'ffffffffffffffffffffffffffffffff');
        $country = $this->country(self::FR_ID);

        static::assertFalse($this->resolver->isExempt(self::business([self::FOREIGN_VAT]), $country, self::CHANNEL_ID));
        static::assertSame(self::FR_ID, $this->resolver->getTaxCountryId(self::business([self::FOREIGN_VAT]), $country, self::CHANNEL_ID));
    }

    #[DataProvider('orderTaxStateProvider')]
    public function testSavedOrderTaxStateControlsTheNote(string $taxState, bool $expected): void
    {
        $country = $this->country(self::FR_ID);
        $country->setCompanyTax(new TaxFreeConfig(true, Defaults::CURRENCY, 100));
        $order = $this->order($country, [self::FOREIGN_VAT], $taxState);

        static::assertSame($expected, $this->resolver->isIntraCommunityDelivery($order));
    }

    public function testDisablingB2bExemptionDoesNotChangeASavedTaxFreeOrder(): void
    {
        $country = $this->country(self::FR_ID);
        $order = $this->order($country, [self::FOREIGN_VAT]);
        static::assertTrue($this->resolver->isIntraCommunityDelivery($order));

        $country->setCompanyTax(new TaxFreeConfig(false));

        static::assertFalse($this->resolver->isExempt(self::business([self::FOREIGN_VAT]), $country, self::CHANNEL_ID));
        static::assertTrue($this->resolver->isIntraCommunityDelivery($order));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function orderTaxStateProvider(): iterable
    {
        yield 'saved tax-free decision is retained despite a new minimum' => [CartPrice::TAX_STATE_FREE, true];
        yield 'taxed net order must not claim an exemption' => [CartPrice::TAX_STATE_NET, false];
        yield 'taxed gross order must not claim an exemption' => [CartPrice::TAX_STATE_GROSS, false];
    }

    public function testDocumentsUseSavedVatIdsInsteadOfTheLiveCustomer(): void
    {
        $order = $this->order($this->country(self::FR_ID), [self::FOREIGN_VAT]);
        $orderCustomer = $order->getOrderCustomer();
        static::assertNotNull($orderCustomer);
        $customer = $orderCustomer->getCustomer();
        static::assertNotNull($customer);
        $customer->setVatIds([self::SELLER_VAT]);

        static::assertTrue($this->resolver->isIntraCommunityDelivery($order));

        $orderCustomer->setVatIds([self::SELLER_VAT]);
        $customer->setVatIds([self::FOREIGN_VAT]);

        static::assertFalse($this->resolver->isIntraCommunityDelivery($order));
    }

    /**
     * @param list<string>|null $vatIds
     */
    #[DataProvider('nonIntraCommunityOrderProvider')]
    public function testTaxFreeStatusAloneDoesNotMakeAnIntraCommunityDelivery(string $countryId, ?array $vatIds, bool $checkVatIdPattern): void
    {
        $country = $this->country($countryId);
        $country->setCheckVatIdPattern($checkVatIdPattern);
        $order = $this->order($country, $vatIds);

        static::assertFalse($this->resolver->isIntraCommunityDelivery($order));
    }

    /**
     * @return iterable<string, array{string, list<string>|null, bool}>
     */
    public static function nonIntraCommunityOrderProvider(): iterable
    {
        yield 'export supply' => [self::CH_ID, [self::FOREIGN_VAT], true];
        yield 'domestic supply' => [self::DE_ID, [self::FOREIGN_VAT], true];
        yield 'seller VAT' => [self::FR_ID, [self::SELLER_VAT], true];
        yield 'VAT of no member state with pattern checks enabled' => [self::FR_ID, ['INVALID'], true];
        yield 'missing VAT with pattern checks enabled' => [self::FR_ID, null, true];
        yield 'missing VAT with pattern checks disabled' => [self::FR_ID, null, false];
        yield 'empty VAT list with pattern checks enabled' => [self::FR_ID, [], true];
        yield 'empty VAT list with pattern checks disabled' => [self::FR_ID, [], false];
    }

    public function testMissingCustomerKeepsTheNoteOff(): void
    {
        $order = $this->order($this->country(self::FR_ID), [self::FOREIGN_VAT]);
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setVatIds([self::FOREIGN_VAT]);
        $order->setOrderCustomer($orderCustomer);

        static::assertFalse($this->resolver->isIntraCommunityDelivery($order));
    }

    public function testMissingDeliveryKeepsTheNoteOff(): void
    {
        $order = $this->order($this->country(self::FR_ID), [self::FOREIGN_VAT]);
        $order->setPrimaryOrderDelivery(null);
        $order->setDeliveries(new OrderDeliveryCollection());

        static::assertFalse($this->resolver->isIntraCommunityDelivery($order));
    }

    public function testDocumentsIgnoreEmptyVatIdEntriesLikeTheCart(): void
    {
        $country = $this->country(self::FR_ID);
        $vatIds = ['', self::FOREIGN_VAT];

        static::assertTrue($this->resolver->isExempt(self::business($vatIds), $country, self::CHANNEL_ID));
        static::assertTrue($this->resolver->isIntraCommunityDelivery($this->order($country, $vatIds)));
    }

    public function testAWarehouseAbroadMovesTheDispatchCountry(): void
    {
        $this->dispatcher->addListener(ResolveDispatchCountryExtension::onPost(), static function (ResolveDispatchCountryExtension $extension): void {
            $extension->result = self::NL_ID;
        });
        $country = $this->country(self::FR_ID);

        // Goods leaving the Netherlands make a German VAT ID foreign and a Dutch one the seller's own
        static::assertTrue($this->resolver->isExempt(self::business([self::SELLER_VAT]), $country, self::CHANNEL_ID));
        static::assertSame(self::FR_ID, $this->resolver->getTaxCountryId(self::business([self::SELLER_VAT]), $country, self::CHANNEL_ID));
        static::assertFalse($this->resolver->isExempt(self::business([self::FOREIGN_VAT]), $country, self::CHANNEL_ID));
        static::assertSame(self::NL_ID, $this->resolver->getTaxCountryId(self::business([self::FOREIGN_VAT]), $country, self::CHANNEL_ID));
    }

    public function testOnlyTheVatIdUsedForTheOrderCounts(): void
    {
        // The customer holds a German and a Dutch VAT ID and buys with the Dutch one
        $customer = self::business([self::SELLER_VAT, self::FOREIGN_VAT]);
        $country = $this->country(self::NL_ID);

        static::assertFalse($this->resolver->isExempt($customer, $country, self::CHANNEL_ID));
        static::assertSame(self::DE_ID, $this->resolver->getTaxCountryId($customer, $country, self::CHANNEL_ID));

        $this->dispatcher->addListener(ResolveVatIdsExtension::onPost(), static function (ResolveVatIdsExtension $extension): void {
            $extension->result = [self::FOREIGN_VAT];
        });

        static::assertTrue($this->resolver->isExempt($customer, $country, self::CHANNEL_ID));
        static::assertSame(self::NL_ID, $this->resolver->getTaxCountryId($customer, $country, self::CHANNEL_ID));
    }

    public function testTheCartPassesTheCustomerToBothExtensions(): void
    {
        $this->captureExtensions();
        $customer = self::business([self::FOREIGN_VAT]);
        $country = $this->country(self::FR_ID);

        $this->resolver->isExempt($customer, $country, self::CHANNEL_ID);

        $this->assertPublished($country, $customer, null);
    }

    public function testDocumentsPassTheOrderToBothExtensions(): void
    {
        $this->captureExtensions();
        $country = $this->country(self::FR_ID);
        $order = $this->order($country, [self::FOREIGN_VAT]);

        $this->resolver->isIntraCommunityDelivery($order);

        $this->assertPublished($country, $order->getOrderCustomer()?->getCustomer(), $order);
    }

    /**
     * @param list<string> $vatIds
     */
    #[DataProvider('intraCommunityVatIdProvider')]
    public function testTheIntraCommunityVatIdIsTheFirstVatIdWithTheCodeOfAMemberState(array $vatIds, ?string $expected): void
    {
        // Without the pattern check a VAT ID without country code makes an intra-community delivery too
        $country = $this->country(self::FR_ID);
        $country->setCheckVatIdPattern(false);

        static::assertSame($expected, $this->resolver->getIntraCommunityVatId($this->order($country, $vatIds)));
    }

    /**
     * @return iterable<string, array{list<string>, string|null}>
     */
    public static function intraCommunityVatIdProvider(): iterable
    {
        yield 'a VAT ID of another member state' => [[self::FOREIGN_VAT], self::FOREIGN_VAT];
        yield 'the first VAT ID with a country code wins' => [['12345678901', '', 'fr12345678901', self::FOREIGN_VAT], 'FR12345678901'];
        yield 'a VAT ID in small letters is written in capitals' => [['nl123456789b01'], 'NL123456789B01'];
        yield 'the Greek EL prefix names a member state' => [['el123456789'], 'EL123456789'];
        yield 'no VAT ID has a country code' => [['12345678901'], null];
    }

    public function testATaxedOrderHasNoIntraCommunityVatId(): void
    {
        $order = $this->order($this->country(self::FR_ID), [self::FOREIGN_VAT], CartPrice::TAX_STATE_NET);

        static::assertNull($this->resolver->getIntraCommunityVatId($order));
    }

    public function testTheIntraCommunityVatIdIsOneThePluginChose(): void
    {
        // The German VAT ID would make the delivery domestic, the plugin decides on the Dutch one
        $order = $this->order($this->country(self::FR_ID), [self::SELLER_VAT, self::FOREIGN_VAT]);
        static::assertNull($this->resolver->getIntraCommunityVatId($order));

        $this->dispatcher->addListener(ResolveVatIdsExtension::onPost(), static function (ResolveVatIdsExtension $extension): void {
            $extension->result = [self::FOREIGN_VAT];
        });

        static::assertSame(self::FOREIGN_VAT, $this->resolver->getIntraCommunityVatId($order));
    }

    public function testADigitalOnlyOrderDecidedByTheBillingAddressIsNoIntraCommunityDelivery(): void
    {
        $this->products = new OrderLineItemCollection([self::product(ProductDefinition::TYPE_DIGITAL)]);
        $order = $this->order($this->country(self::FR_ID), [self::FOREIGN_VAT]);

        static::assertFalse($this->resolver->isIntraCommunityDelivery($order));
        static::assertNull($this->resolver->getIntraCommunityVatId($order));
    }

    #[DataProvider('shippingDecidedDigitalOrderProvider')]
    public function testADigitalOnlyOrderOutsideTheBillingOptInStatesTheIntraCommunityDelivery(string $billingCountryId, bool $billingCompanyTaxFree): void
    {
        $this->products = new OrderLineItemCollection([self::product(ProductDefinition::TYPE_DIGITAL)]);
        $billingCountry = $this->country($billingCountryId);
        $billingCountry->setCompanyTax(new TaxFreeConfig($billingCompanyTaxFree));
        $order = $this->order($this->country(self::FR_ID), [self::FOREIGN_VAT], billingCountry: $billingCountry);

        static::assertFalse($this->resolver->isReverseCharge($order));
        static::assertNull($this->resolver->getReverseChargeVatId($order));
        static::assertTrue($this->resolver->isIntraCommunityDelivery($order));
        static::assertSame(self::FOREIGN_VAT, $this->resolver->getIntraCommunityVatId($order));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function shippingDecidedDigitalOrderProvider(): iterable
    {
        yield 'billed outside the EU' => [self::CH_ID, true];
        yield 'billing country without Tax-free (B2B)' => [self::NL_ID, false];
    }

    /**
     * @param list<string>|null $vatIds
     */
    #[DataProvider('reverseChargeProvider')]
    public function testReverseChargeAppliesTheIntraCommunityRulesToTheBillingAddress(string $billingCountryId, ?array $vatIds, bool $expected): void
    {
        $this->products = new OrderLineItemCollection([self::product(ProductDefinition::TYPE_DIGITAL)]);
        $order = $this->order($this->country(self::DE_ID), $vatIds, billingCountry: $this->country($billingCountryId));

        static::assertSame($expected, $this->resolver->isReverseCharge($order));
        static::assertFalse($this->resolver->isIntraCommunityDelivery($order));
    }

    /**
     * @return iterable<string, array{string, list<string>|null, bool}>
     */
    public static function reverseChargeProvider(): iterable
    {
        yield 'a VAT ID of another member state billed to a third member state' => [self::FR_ID, [self::FOREIGN_VAT], true];
        yield 'a VAT ID of the billing country' => [self::FR_ID, ['FR12345678901'], true];
        yield 'billed to the seller country' => [self::DE_ID, [self::FOREIGN_VAT], false];
        yield 'billed outside the EU' => [self::CH_ID, [self::FOREIGN_VAT], false];
        yield 'the seller VAT ID' => [self::FR_ID, [self::SELLER_VAT], false];
        yield 'no VAT ID' => [self::FR_ID, null, false];
        yield 'a VAT ID of no member state' => [self::FR_ID, ['INVALID'], false];
    }

    #[DataProvider('taxedOrderStateProvider')]
    public function testATaxedDigitalOnlyOrderIsNoReverseCharge(string $taxState): void
    {
        $this->products = new OrderLineItemCollection([self::product(ProductDefinition::TYPE_DIGITAL)]);
        $order = $this->order($this->country(self::DE_ID), [self::FOREIGN_VAT], $taxState, $this->country(self::FR_ID));

        static::assertFalse($this->resolver->isReverseCharge($order));
        static::assertNull($this->resolver->getReverseChargeVatId($order));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function taxedOrderStateProvider(): iterable
    {
        yield 'net order' => [CartPrice::TAX_STATE_NET];
        yield 'gross order' => [CartPrice::TAX_STATE_GROSS];
    }

    public function testAPrivateCustomerGetsNoReverseCharge(): void
    {
        $this->products = new OrderLineItemCollection([self::product(ProductDefinition::TYPE_DIGITAL)]);
        $order = $this->order($this->country(self::DE_ID), [self::FOREIGN_VAT], billingCountry: $this->country(self::FR_ID));
        $order->getOrderCustomer()?->getCustomer()?->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);

        static::assertFalse($this->resolver->isReverseCharge($order));
    }

    /**
     * @param list<OrderLineItemEntity> $products
     */
    #[DataProvider('productsProvider')]
    public function testOnlyAnOrderWhoseProductsAreAllDigitalIsDecidedByTheBillingAddress(array $products, bool $isDigitalOnly): void
    {
        $this->products = new OrderLineItemCollection($products);
        $order = $this->order($this->country(self::FR_ID), [self::FOREIGN_VAT]);

        static::assertSame($isDigitalOnly, $this->resolver->isReverseCharge($order));
        static::assertSame(!$isDigitalOnly, $this->resolver->isIntraCommunityDelivery($order));
    }

    /**
     * @return iterable<string, array{list<OrderLineItemEntity>, bool}>
     */
    public static function productsProvider(): iterable
    {
        yield 'every product is digital' => [[self::product(ProductDefinition::TYPE_DIGITAL), self::product(ProductDefinition::TYPE_DIGITAL)], true];
        yield 'a digital and a physical product' => [[self::product(ProductDefinition::TYPE_DIGITAL), self::product(ProductDefinition::TYPE_PHYSICAL)], false];
        yield 'a product without type counts as physical' => [[self::product(ProductDefinition::TYPE_DIGITAL), self::product(null)], false];
        yield 'an order without products' => [[], false];
    }

    public function testTheProductsAreReadWithTheirNestedOnesFromTheOrdersVersion(): void
    {
        $this->products = new OrderLineItemCollection([self::product(ProductDefinition::TYPE_DIGITAL)]);
        $versionIds = [];
        $this->orderLineItemRepository->searches = [function (Criteria $criteria, Context $context) use (&$versionIds): OrderLineItemCollection {
            $versionIds[] = $context->getVersionId();

            return $this->products;
        }];

        $this->resolver->isReverseCharge($this->order($this->country(self::FR_ID), [self::FOREIGN_VAT]));

        static::assertSame([self::ORDER_VERSION_ID], $versionIds);
        static::assertEquals(
            [new EqualsFilter('orderId', self::ORDER_ID), new EqualsFilter('type', LineItem::PRODUCT_LINE_ITEM_TYPE)],
            $this->orderLineItemRepository->searchedCriteria[0]->getFilters()
        );
    }

    public function testTheProductsAreReadOncePerOrderVersionUntilReset(): void
    {
        $this->orderLineItemRepository->searches[] = fn (): OrderLineItemCollection => $this->products;
        $order = $this->order($this->country(self::FR_ID), [self::FOREIGN_VAT]);

        // The renderers, the e-invoice and the note ask several times per document
        $this->resolver->isIntraCommunityDelivery($order);
        $this->resolver->getIntraCommunityVatId($order);
        $this->resolver->isReverseCharge($order);
        static::assertCount(1, $this->orderLineItemRepository->searchedCriteria);

        $this->resolver->reset();
        $this->resolver->isIntraCommunityDelivery($order);
        static::assertCount(2, $this->orderLineItemRepository->searchedCriteria);
    }

    public function testReverseChargePassesTheBillingCountryAndTheOrderToBothExtensions(): void
    {
        $this->products = new OrderLineItemCollection([self::product(ProductDefinition::TYPE_DIGITAL)]);
        $this->captureExtensions();
        $billingCountry = $this->country(self::FR_ID);
        $order = $this->order($this->country(self::DE_ID), [self::FOREIGN_VAT], billingCountry: $billingCountry);

        $this->resolver->isReverseCharge($order);

        // The billing opt-in reads the dispatch country first
        $this->assertPublished($billingCountry, $order->getOrderCustomer()?->getCustomer(), $order, [
            ResolveDispatchCountryExtension::class,
            ResolveVatIdsExtension::class,
            ResolveDispatchCountryExtension::class,
        ]);
    }

    public function testADispatchCountryInTheBuyersMemberStateIsNoReverseCharge(): void
    {
        $this->products = new OrderLineItemCollection([self::product(ProductDefinition::TYPE_DIGITAL)]);
        $order = $this->order($this->country(self::DE_ID), [self::FOREIGN_VAT], billingCountry: $this->country(self::FR_ID));
        static::assertTrue($this->resolver->isReverseCharge($order));

        $this->dispatcher->addListener(ResolveDispatchCountryExtension::onPost(), static function (ResolveDispatchCountryExtension $extension): void {
            $extension->result = self::FR_ID;
        });

        static::assertFalse($this->resolver->isReverseCharge($order));
    }

    /**
     * @param list<string> $vatIds
     */
    #[DataProvider('intraCommunityVatIdProvider')]
    public function testTheReverseChargeVatIdIsTheFirstVatIdWithTheCodeOfAMemberState(array $vatIds, ?string $expected): void
    {
        $this->products = new OrderLineItemCollection([self::product(ProductDefinition::TYPE_DIGITAL)]);
        $billingCountry = $this->country(self::FR_ID);
        $billingCountry->setCheckVatIdPattern(false);

        static::assertSame($expected, $this->resolver->getReverseChargeVatId($this->order($this->country(self::DE_ID), $vatIds, billingCountry: $billingCountry)));
    }

    public function testAnOrderWithAPhysicalProductHasNoReverseChargeVatId(): void
    {
        $order = $this->order($this->country(self::DE_ID), [self::FOREIGN_VAT], billingCountry: $this->country(self::FR_ID));

        static::assertNull($this->resolver->getReverseChargeVatId($order));
    }

    public function testReverseChargeReadsTheBillingAddressAmongTheOrderAddresses(): void
    {
        $this->products = new OrderLineItemCollection([self::product(ProductDefinition::TYPE_DIGITAL)]);
        $order = $this->order($this->country(self::DE_ID), [self::FOREIGN_VAT], billingCountry: $this->country(self::FR_ID));
        $billingAddress = $order->getBillingAddress();
        static::assertNotNull($billingAddress);
        $order->assign(['billingAddress' => null]);
        $order->setBillingAddressId($billingAddress->getId());
        $order->setAddresses(new OrderAddressCollection([$billingAddress]));

        static::assertTrue($this->resolver->isReverseCharge($order));
    }

    public function testTheShippingAddressIsThatOfThePrimaryDelivery(): void
    {
        $order = $this->orderWithTwoDeliveries();

        static::assertSame('primary', $this->resolver->getShippingAddress($order)?->getId());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testTheShippingAddressIsThatOfTheFirstDeliveryWithoutTheMajorFlag(): void
    {
        $order = $this->orderWithTwoDeliveries();

        static::assertSame('first', $this->resolver->getShippingAddress($order)?->getId());

        // The ZUGFeRD credit note drops the deliveries of the order it bills
        $order->setDeliveries(new OrderDeliveryCollection());

        static::assertSame('primary', $this->resolver->getShippingAddress($order)?->getId());
    }

    private function orderWithTwoDeliveries(): OrderEntity
    {
        $order = $this->order($this->country(self::FR_ID), [self::FOREIGN_VAT]);
        $primaryDelivery = $order->getPrimaryOrderDelivery();
        static::assertNotNull($primaryDelivery);
        $primaryDelivery->getShippingOrderAddress()?->setId('primary');

        $firstAddress = new OrderAddressEntity();
        $firstAddress->setId('first');
        $firstDelivery = new OrderDeliveryEntity();
        $firstDelivery->setId('cccccccccccccccccccccccccccccccc');
        $firstDelivery->setShippingOrderAddress($firstAddress);
        $order->setDeliveries(new OrderDeliveryCollection([$firstDelivery, $primaryDelivery]));

        return $order;
    }

    /**
     * @param list<class-string> $extensions
     */
    private function assertPublished(
        CountryEntity $country,
        ?CustomerEntity $customer,
        ?OrderEntity $order,
        array $extensions = [ResolveVatIdsExtension::class, ResolveDispatchCountryExtension::class],
    ): void {
        static::assertSame($extensions, array_map(get_class(...), $this->published));
        foreach ($this->published as $extension) {
            static::assertSame(self::CHANNEL_ID, $extension->salesChannelId);
            static::assertSame($country, $extension->taxCountry);
            static::assertSame($customer, $extension->customer);
            static::assertSame($order, $extension->order);
        }
    }

    private function captureExtensions(): void
    {
        $capture = function (ResolveDispatchCountryExtension|ResolveVatIdsExtension $extension): void {
            $this->published[] = $extension;
        };
        $this->dispatcher->addListener(ResolveVatIdsExtension::onPre(), $capture);
        $this->dispatcher->addListener(ResolveDispatchCountryExtension::onPre(), $capture);
    }

    /**
     * @param list<string>|null $vatIds
     */
    private static function business(?array $vatIds): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_BUSINESS);
        $customer->setVatIds($vatIds);

        return $customer;
    }

    private function country(string $id): CountryEntity
    {
        $country = $this->countries->get($id);
        static::assertNotNull($country);

        return $country;
    }

    /**
     * @param list<string>|null $vatIds
     */
    private function order(CountryEntity $country, ?array $vatIds, string $taxState = CartPrice::TAX_STATE_FREE, ?CountryEntity $billingCountry = null): OrderEntity
    {
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setCustomer(self::business(null));
        if ($vatIds !== null) {
            $orderCustomer->setVatIds($vatIds);
        }
        $address = new OrderAddressEntity();
        $address->setCountry($country);
        $delivery = new OrderDeliveryEntity();
        $delivery->setId('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
        $delivery->setShippingOrderAddress($address);
        $billingAddress = new OrderAddressEntity();
        $billingAddress->setId('ffffffffffffffffffffffffffffffff');
        $billingAddress->setCountry($billingCountry ?? $country);
        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setVersionId(self::ORDER_VERSION_ID);
        $order->setSalesChannelId(self::CHANNEL_ID);
        $order->setBillingAddress($billingAddress);
        $order->setOrderCustomer($orderCustomer);
        $order->setPrice(new CartPrice(50.0, 50.0, 50.0, new CalculatedTaxCollection(), new TaxRuleCollection(), $taxState));
        $order->setPrimaryOrderDelivery($delivery);
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        return $order;
    }

    private static function product(?string $productType): OrderLineItemEntity
    {
        $product = new OrderLineItemEntity();
        $product->setId(Uuid::randomHex());
        $product->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        if ($productType !== null) {
            $product->setPayload([LineItem::PAYLOAD_PRODUCT_TYPE => $productType]);
        }

        return $product;
    }
}
