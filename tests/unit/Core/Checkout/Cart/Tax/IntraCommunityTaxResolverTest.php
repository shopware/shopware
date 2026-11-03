<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Tax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\IntraCommunityTaxResolver;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
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
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

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

    private CountryCollection $countries;

    private StaticSystemConfigService $systemConfig;

    private IntraCommunityTaxResolver $resolver;

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
        $this->resolver = new IntraCommunityTaxResolver(
            new VatIdPatternProvider(new StaticEntityRepository([$euCountries])),
            $this->systemConfig,
            StaticEntityRepository::of(CountryCollection::class, [[self::DE_ID]]),
        );
    }

    /**
     * @param list<string>|null $vatIds
     */
    #[DataProvider('goodsMatrixProvider')]
    public function testGoodsMatrix(?array $vatIds, string $deliveryCountryId, bool $exempt, string $taxCountryId): void
    {
        $country = $this->country($deliveryCountryId);

        static::assertSame($exempt, $this->resolver->isExempt(CustomerEntity::ACCOUNT_TYPE_BUSINESS, $vatIds, $country, self::CHANNEL_ID));
        static::assertSame($taxCountryId, $this->resolver->getTaxCountryId(CustomerEntity::ACCOUNT_TYPE_BUSINESS, $vatIds, $country, self::CHANNEL_ID));
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

        static::assertSame($exempt, $this->resolver->isExempt(CustomerEntity::ACCOUNT_TYPE_BUSINESS, $vatIds, $country, self::CHANNEL_ID));
        static::assertSame($taxCountryId, $this->resolver->getTaxCountryId(CustomerEntity::ACCOUNT_TYPE_BUSINESS, $vatIds, $country, self::CHANNEL_ID));
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

    #[DataProvider('accountTypeProvider')]
    public function testOriginVatRequiresABusinessAccount(?string $accountType): void
    {
        $country = $this->country(self::FR_ID);

        static::assertFalse($this->resolver->isExempt($accountType, [self::SELLER_VAT], $country, self::CHANNEL_ID));
        static::assertSame(self::FR_ID, $this->resolver->getTaxCountryId($accountType, [self::SELLER_VAT], $country, self::CHANNEL_ID));
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function accountTypeProvider(): iterable
    {
        yield 'private account' => [CustomerEntity::ACCOUNT_TYPE_PRIVATE];
        yield 'missing account' => [null];
    }

    public function testDisabledCompanyTaxKeepsDestinationRules(): void
    {
        $country = $this->country(self::FR_ID);
        $country->setCompanyTax(new TaxFreeConfig(false));

        static::assertFalse($this->resolver->isExempt(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::FOREIGN_VAT], $country, self::CHANNEL_ID));
        static::assertSame(self::FR_ID, $this->resolver->getTaxCountryId(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::SELLER_VAT], $country, self::CHANNEL_ID));
    }

    public function testConfiguredSellerOverridesTheSalesChannelDefault(): void
    {
        $this->systemConfig->set('core.basicInformation.sellerCountryId', self::NL_ID);
        $country = $this->country(self::FR_ID);

        static::assertFalse($this->resolver->isExempt(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::FOREIGN_VAT], $country, self::CHANNEL_ID));
        static::assertSame(self::NL_ID, $this->resolver->getTaxCountryId(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::FOREIGN_VAT], $country, self::CHANNEL_ID));
    }

    public function testMissingSellerConfigurationUsesTheSalesChannelDefault(): void
    {
        $this->systemConfig->set('core.basicInformation.sellerCountryId', '');
        $country = $this->country(self::FR_ID);

        static::assertSame(self::DE_ID, $this->resolver->getTaxCountryId(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::SELLER_VAT], $country, self::CHANNEL_ID));
        static::assertFalse($this->resolver->isExempt(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::SELLER_VAT], $country, self::CHANNEL_ID));
    }

    public function testAnUppercaseSellerCountryIdStillNamesTheSellersMemberState(): void
    {
        $this->systemConfig->set('core.basicInformation.sellerCountryId', strtoupper(self::DE_ID));
        $country = $this->country(self::FR_ID);

        static::assertSame(self::DE_ID, $this->resolver->getTaxCountryId(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::SELLER_VAT], $country, self::CHANNEL_ID));
        static::assertTrue($this->resolver->isExempt(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::FOREIGN_VAT], $country, self::CHANNEL_ID));
    }

    public function testAnEmptySalesChannelHasNoSellerState(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->never())->method('getString');
        $resolver = new IntraCommunityTaxResolver(
            new VatIdPatternProvider(new StaticEntityRepository([$this->countries->filter(static fn (CountryEntity $country) => $country->getIsEu())])),
            $systemConfig,
            StaticEntityRepository::of(CountryCollection::class),
        );
        $country = $this->country(self::FR_ID);

        static::assertFalse($resolver->isExempt(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::FOREIGN_VAT], $country, ''));
        static::assertSame(self::FR_ID, $resolver->getTaxCountryId(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::SELLER_VAT], $country, ''));
    }

    public function testTheSalesChannelsDefaultCountryIsReadOncePerSalesChannelUntilReset(): void
    {
        $this->systemConfig->set('core.basicInformation.sellerCountryId', '');
        $countryRepository = StaticEntityRepository::of(CountryCollection::class, [[self::DE_ID], [self::DE_ID]]);
        $resolver = new IntraCommunityTaxResolver(
            new VatIdPatternProvider(new StaticEntityRepository([$this->countries->filter(static fn (CountryEntity $country) => $country->getIsEu())])),
            $this->systemConfig,
            $countryRepository,
        );
        $country = $this->country(self::FR_ID);

        // The context factory and the cart ask several times per request
        $resolver->getTaxCountryId(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::SELLER_VAT], $country, self::CHANNEL_ID);
        $resolver->isExempt(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::SELLER_VAT], $country, self::CHANNEL_ID);
        static::assertCount(1, $countryRepository->searchedCriteria);

        $resolver->reset();
        $resolver->isExempt(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::SELLER_VAT], $country, self::CHANNEL_ID);
        static::assertCount(2, $countryRepository->searchedCriteria);
    }

    public function testUnknownSellerDoesNotGrantAnIntraCommunityExemption(): void
    {
        $this->systemConfig->set('core.basicInformation.sellerCountryId', 'ffffffffffffffffffffffffffffffff');
        $country = $this->country(self::FR_ID);

        static::assertFalse($this->resolver->isExempt(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::FOREIGN_VAT], $country, self::CHANNEL_ID));
        static::assertSame(self::FR_ID, $this->resolver->getTaxCountryId(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::FOREIGN_VAT], $country, self::CHANNEL_ID));
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

        static::assertFalse($this->resolver->isExempt(CustomerEntity::ACCOUNT_TYPE_BUSINESS, [self::FOREIGN_VAT], $country, self::CHANNEL_ID));
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

        static::assertTrue($this->resolver->isExempt(CustomerEntity::ACCOUNT_TYPE_BUSINESS, $vatIds, $country, self::CHANNEL_ID));
        static::assertTrue($this->resolver->isIntraCommunityDelivery($this->order($country, $vatIds)));
    }

    /**
     * @param list<array{string, float}> $lineItems product type and tax rate of each line item
     */
    #[DataProvider('exemptPhysicalGoodsProvider')]
    public function testTaxedOrderNotesTheExemptPhysicalGoods(array $lineItems, bool $expected): void
    {
        $order = $this->order($this->country(self::FR_ID), [self::FOREIGN_VAT], CartPrice::TAX_STATE_GROSS, $lineItems);

        static::assertSame($expected, $this->resolver->isIntraCommunityDelivery($order));
    }

    /**
     * @return iterable<string, array{list<array{string, float}>, bool}>
     */
    public static function exemptPhysicalGoodsProvider(): iterable
    {
        yield 'physical goods at 0 % next to taxed digital products' => [[[ProductDefinition::TYPE_PHYSICAL, 0.0], [ProductDefinition::TYPE_DIGITAL, 21.0]], true];
        yield 'custom product type counts as physical goods' => [[['custom-type', 0.0], [ProductDefinition::TYPE_DIGITAL, 21.0]], true];
        yield 'taxed physical goods next to exempt digital products' => [[[ProductDefinition::TYPE_PHYSICAL, 20.0], [ProductDefinition::TYPE_DIGITAL, 0.0]], false];
        yield 'only digital products' => [[[ProductDefinition::TYPE_DIGITAL, 0.0]], false];
    }

    /**
     * @param list<array{string, float}> $lineItems product type and tax rate of each line item
     * @param list<string> $vatIds
     */
    #[DataProvider('reverseChargeProvider')]
    public function testReverseCharge(string $taxState, array $lineItems, string $billingCountryId, array $vatIds, bool $expected): void
    {
        $order = $this->order($this->country(self::FR_ID), $vatIds, $taxState, $lineItems, $this->country($billingCountryId));

        static::assertSame($expected, $this->resolver->isReverseCharge($order));
    }

    /**
     * @return iterable<string, array{string, list<array{string, float}>, string, list<string>, bool}>
     */
    public static function reverseChargeProvider(): iterable
    {
        $exemptDigital = [ProductDefinition::TYPE_DIGITAL, 0.0];

        yield 'tax-free digital order of a business in another member state' => [CartPrice::TAX_STATE_FREE, [$exemptDigital], self::NL_ID, [self::FOREIGN_VAT], true];
        yield 'taxed order with exempt digital products' => [CartPrice::TAX_STATE_GROSS, [[ProductDefinition::TYPE_PHYSICAL, 19.0], $exemptDigital], self::NL_ID, [self::FOREIGN_VAT], true];
        yield 'taxed digital products' => [CartPrice::TAX_STATE_GROSS, [[ProductDefinition::TYPE_DIGITAL, 21.0]], self::NL_ID, [self::FOREIGN_VAT], false];
        yield 'tax-free order without digital products' => [CartPrice::TAX_STATE_FREE, [[ProductDefinition::TYPE_PHYSICAL, 0.0]], self::NL_ID, [self::FOREIGN_VAT], false];
        yield 'billing country outside the EU' => [CartPrice::TAX_STATE_FREE, [$exemptDigital], self::CH_ID, [self::FOREIGN_VAT], false];
        yield 'billing country is the seller state' => [CartPrice::TAX_STATE_FREE, [$exemptDigital], self::DE_ID, [self::FOREIGN_VAT], false];
        yield 'VAT ID of the seller state' => [CartPrice::TAX_STATE_FREE, [$exemptDigital], self::NL_ID, [self::SELLER_VAT], false];
    }

    public function testReverseChargeRequiresABusiness(): void
    {
        $order = $this->order($this->country(self::FR_ID), [self::FOREIGN_VAT], CartPrice::TAX_STATE_FREE, [[ProductDefinition::TYPE_DIGITAL, 0.0]], $this->country(self::NL_ID));
        $order->getOrderCustomer()?->getCustomer()?->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);

        static::assertFalse($this->resolver->isReverseCharge($order));
    }

    private function country(string $id): CountryEntity
    {
        $country = $this->countries->get($id);
        static::assertNotNull($country);

        return $country;
    }

    /**
     * @param list<string>|null $vatIds
     * @param list<array{string, float}> $lineItems product type and tax rate of each line item
     */
    private function order(
        CountryEntity $country,
        ?array $vatIds,
        string $taxState = CartPrice::TAX_STATE_FREE,
        array $lineItems = [],
        ?CountryEntity $billingCountry = null,
    ): OrderEntity {
        $customer = new CustomerEntity();
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_BUSINESS);
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setCustomer($customer);
        if ($vatIds !== null) {
            $orderCustomer->setVatIds($vatIds);
        }
        $address = new OrderAddressEntity();
        $address->setCountry($country);
        $delivery = new OrderDeliveryEntity();
        $delivery->setId('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
        $delivery->setShippingOrderAddress($address);
        $order = new OrderEntity();
        $order->setSalesChannelId(self::CHANNEL_ID);
        $order->setOrderCustomer($orderCustomer);
        $order->setPrice(new CartPrice(50.0, 50.0, 50.0, new CalculatedTaxCollection(), new TaxRuleCollection(), $taxState));
        $order->setPrimaryOrderDelivery($delivery);
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        $billingAddress = new OrderAddressEntity();
        $billingAddress->setId('cccccccccccccccccccccccccccccccc');
        $billingAddress->setCountry($billingCountry ?? $country);
        $order->setBillingAddressId($billingAddress->getId());
        $order->setAddresses(new OrderAddressCollection([$billingAddress]));

        $orderLineItems = new OrderLineItemCollection();
        foreach ($lineItems as $index => [$productType, $taxRate]) {
            $lineItem = new OrderLineItemEntity();
            $lineItem->setId(str_repeat((string) $index, 32));
            $lineItem->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
            $lineItem->setPayload([LineItem::PAYLOAD_PRODUCT_TYPE => $productType]);
            $lineItem->setPrice(new CalculatedPrice(
                10.0,
                10.0,
                new CalculatedTaxCollection([new CalculatedTax($taxRate / 10, $taxRate, 10.0)]),
                new TaxRuleCollection([new TaxRule($taxRate)]),
            ));
            $orderLineItems->add($lineItem);
        }
        $order->setLineItems($orderLineItems);

        return $order;
    }
}
