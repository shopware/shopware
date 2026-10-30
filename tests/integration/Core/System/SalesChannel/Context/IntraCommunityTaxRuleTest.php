<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\IntraCommunityTaxResolver;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\Tax\TaxRuleType\EntireCountryRuleTypeFilter;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('discovery')]
class IntraCommunityTaxRuleTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    /**
     * @var array<string, string>
     */
    private array $countryIds;

    private string $customerId;

    private string $taxId;

    private string $shippingStateId;

    private string $shippingAddressId;

    protected function setUp(): void
    {
        $context = Context::createDefaultContext();
        $container = static::getContainer();
        $this->customerRepository = $container->get('customer.repository');

        foreach (['DE', 'FR', 'NL', 'CH'] as $iso) {
            $countryId = $container->get('country.repository')->searchIds(
                (new Criteria())->addFilter(new EqualsFilter('iso', $iso)),
                $context
            )->firstId();
            static::assertNotNull($countryId);
            $this->countryIds[$iso] = $countryId;
        }

        $container->get('sales_channel.repository')->update([[
            'id' => TestDefaults::SALES_CHANNEL,
            'countryId' => $this->countryIds['DE'],
        ]], $context);

        $container->get('country.repository')->update([[
            'id' => $this->countryIds['FR'],
            'isEu' => true,
            'checkVatIdPattern' => true,
            'customerTax' => ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
            'companyTax' => ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
        ]], $context);

        $ruleTypeId = $container->get('tax_rule_type.repository')->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', EntireCountryRuleTypeFilter::TECHNICAL_NAME)),
            $context
        )->firstId();
        static::assertNotNull($ruleTypeId);

        $this->taxId = Uuid::randomHex();
        $container->get('tax.repository')->create([[
            'id' => $this->taxId,
            'name' => 'Intra-EU tax test',
            'taxRate' => 15,
            'rules' => [
                ['countryId' => $this->countryIds['DE'], 'taxRate' => 19, 'taxRuleTypeId' => $ruleTypeId],
                ['countryId' => $this->countryIds['FR'], 'taxRate' => 20, 'taxRuleTypeId' => $ruleTypeId],
                ['countryId' => $this->countryIds['NL'], 'taxRate' => 21, 'taxRuleTypeId' => $ruleTypeId],
                ['countryId' => $this->countryIds['CH'], 'taxRate' => 8.1, 'taxRuleTypeId' => $ruleTypeId],
            ],
        ]], $context);

        $this->shippingStateId = Uuid::randomHex();
        $container->get('country_state.repository')->create([[
            'id' => $this->shippingStateId,
            'countryId' => $this->countryIds['FR'],
            'shortCode' => 'FR-TEST',
            'name' => 'French shipping state',
        ]], $context);

        $this->customerId = Uuid::randomHex();
        $this->shippingAddressId = Uuid::randomHex();
        $this->customerRepository->create([[
            'id' => $this->customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => 'Intra-EU tax test',
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $this->customerId . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => $this->customerId,
            'defaultBillingAddress' => [
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Billing Street 1',
                'city' => 'Amsterdam',
                'zipcode' => '1011',
                'countryId' => $this->countryIds['NL'],
            ],
            'defaultShippingAddress' => [
                'id' => $this->shippingAddressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Shipping Street 1',
                'city' => 'Paris',
                'zipcode' => '75001',
                'countryId' => $this->countryIds['FR'],
                'countryStateId' => $this->shippingStateId,
            ],
        ]], $context);

        $container->get(SystemConfigService::class)->set('core.basicInformation.sellerCountryId', $this->countryIds['DE']);
    }

    public function testConfiguredSellerOverridesSalesChannelDefaultCountryAndDefaultTaxRate(): void
    {
        static::getContainer()->get(SystemConfigService::class)->set('core.basicInformation.sellerCountryId', $this->countryIds['NL']);
        $this->customerRepository->update([[
            'id' => $this->customerId,
            'vatIds' => ['NL123456789B01'],
        ]], Context::createDefaultContext());

        $context = $this->createContext();

        static::assertSame($this->countryIds['DE'], $context->getSalesChannel()->getCountryId());
        static::assertSame(21.0, $context->buildTaxRules($this->taxId)->first()?->getTaxRate());
        static::assertSame(CartPrice::TAX_STATE_GROSS, $context->getTaxState());
        static::assertSame($this->countryIds['FR'], $context->getShippingLocation()->getCountry()->getId());
    }

    public function testMissingSellerCountryFallsBackToSalesChannelDefaultCountry(): void
    {
        static::getContainer()->get(SystemConfigService::class)->set('core.basicInformation.sellerCountryId', null);
        $this->customerRepository->update([[
            'id' => $this->customerId,
            'vatIds' => ['DE123456789'],
        ]], Context::createDefaultContext());

        $context = $this->createContext();

        static::assertSame(19.0, $context->buildTaxRules($this->taxId)->first()?->getTaxRate());
        static::assertSame(CartPrice::TAX_STATE_GROSS, $context->getTaxState());
        static::assertSame($this->countryIds['FR'], $context->getShippingLocation()->getCountry()->getId());
    }

    public function testPrivateCustomerWithSellerVatIdUsesDestinationTaxRule(): void
    {
        $this->customerRepository->update([[
            'id' => $this->customerId,
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'vatIds' => ['DE123456789'],
        ]], Context::createDefaultContext());

        $context = $this->createContext();

        static::assertSame(20.0, $context->buildTaxRules($this->taxId)->first()?->getTaxRate());
        static::assertSame(CartPrice::TAX_STATE_GROSS, $context->getTaxState());
    }

    #[DataProvider('taxableCustomerGroupProvider')]
    public function testSelfSuppliedSellerVatIdKeepsDestinationTaxWhenCompanyTaxFreeIsDisabled(bool $displayGross, string $taxState, float $price): void
    {
        $container = static::getContainer();
        $container->get('country.repository')->update([
            [
                'id' => $this->countryIds['FR'],
                'companyTax' => ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
            ],
            [
                'id' => $this->countryIds['NL'],
                'checkVatIdPattern' => true,
                'vatIdPattern' => 'NL\d{9}B\d{2}',
            ],
        ], Context::createDefaultContext());

        $customerGroupId = Uuid::randomHex();
        $container->get('customer_group.repository')->create([[
            'id' => $customerGroupId,
            'name' => 'Taxable business customer',
            'displayGross' => $displayGross,
        ]], Context::createDefaultContext());
        $this->customerRepository->update([[
            'id' => $this->customerId,
            'groupId' => $customerGroupId,
        ]], Context::createDefaultContext());

        $context = $this->createContext();
        $customer = $context->getCustomer();
        static::assertNotNull($customer);
        $response = $container->get(ChangeCustomerProfileRoute::class)->change(new RequestDataBag([
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => 'Self-declared business',
            'vatIds' => ['DE123456789'],
        ]), $context, $customer);
        static::assertTrue($response->getObject()->get('success'));

        $context = $this->createContext();
        $updatedCustomer = $context->getCustomer();
        static::assertNotNull($updatedCustomer);

        static::assertSame(['DE123456789'], $updatedCustomer->getVatIds());
        static::assertSame($this->countryIds['DE'], $updatedCustomer->getVatIdCountryId());
        static::assertFalse($context->getShippingLocation()->getCountry()->getCompanyTax()->getEnabled());
        static::assertSame($taxState, $context->getTaxState());
        static::assertSame($this->countryIds['FR'], $context->getShippingLocation()->getCountry()->getId());
        static::assertSame($this->shippingAddressId, $context->getShippingLocation()->getAddress()?->getId());
        static::assertSame(20.0, $context->buildTaxRules($this->taxId)->first()?->getTaxRate());

        $calculatedPrice = $container->get(QuantityPriceCalculator::class)->calculate(
            new QuantityPriceDefinition($price, $context->buildTaxRules($this->taxId)),
            $context
        );

        $calculatedTax = $calculatedPrice->getCalculatedTaxes()->first();
        static::assertNotNull($calculatedTax);
        static::assertSame(20.0, $calculatedTax->getTaxRate());
        static::assertSame(20.0, $calculatedTax->getTax());
    }

    /**
     * @return iterable<string, array{bool, string, float}>
     */
    public static function taxableCustomerGroupProvider(): iterable
    {
        yield 'gross customer keeps destination VAT' => [true, CartPrice::TAX_STATE_GROSS, 120.0];
        yield 'net customer keeps destination VAT' => [false, CartPrice::TAX_STATE_NET, 100.0];
    }

    /**
     * @param list<string> $vatIds
     */
    #[DataProvider('goodsMovementTaxMatrixProvider')]
    public function testGoodsMovementTaxMatrix(array $vatIds, string $deliveryCountry, string $expectedTaxState, float $expectedTaxRule): void
    {
        $this->customerRepository->update([[
            'id' => $this->customerId,
            'vatIds' => $vatIds,
        ]], Context::createDefaultContext());
        static::getContainer()->get('country.repository')->update([[
            'id' => $this->countryIds[$deliveryCountry],
            'isEu' => $deliveryCountry !== 'CH',
            'checkVatIdPattern' => true,
            'customerTax' => ['enabled' => $deliveryCountry === 'CH', 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
            'companyTax' => ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
        ]], Context::createDefaultContext());
        static::getContainer()->get('customer_address.repository')->update([[
            'id' => $this->shippingAddressId,
            'countryId' => $this->countryIds[$deliveryCountry],
            'countryStateId' => null,
        ]], Context::createDefaultContext());

        $context = $this->createContext();

        static::assertSame($expectedTaxState, $context->getTaxState());
        static::assertSame($expectedTaxRule, $context->buildTaxRules($this->taxId)->first()?->getTaxRate());
        static::assertSame($this->countryIds[$deliveryCountry], $context->getShippingLocation()->getCountry()->getId());
    }

    /**
     * @return iterable<string, array{list<string>, string, string, float}>
     */
    public static function goodsMovementTaxMatrixProvider(): iterable
    {
        yield 'foreign EU VAT ID, export is exempt' => [['NL123456789B01'], 'CH', CartPrice::TAX_STATE_FREE, 8.1];
        yield 'foreign EU VAT ID, intra-community supply is exempt' => [['NL123456789B01'], 'FR', CartPrice::TAX_STATE_FREE, 20.0];
        yield 'foreign EU VAT ID, domestic supply uses seller tax' => [['NL123456789B01'], 'DE', CartPrice::TAX_STATE_GROSS, 19.0];
        yield 'delivery country VAT ID, intra-community supply is exempt' => [['FRAB123456789'], 'FR', CartPrice::TAX_STATE_FREE, 20.0];
        yield 'seller EU VAT ID, export is exempt' => [['DE123456789'], 'CH', CartPrice::TAX_STATE_FREE, 8.1];
        yield 'seller EU VAT ID, intra-EU supply uses seller tax' => [['DE123456789'], 'FR', CartPrice::TAX_STATE_GROSS, 19.0];
        yield 'seller EU VAT ID, domestic supply uses seller tax' => [['DE123456789'], 'DE', CartPrice::TAX_STATE_GROSS, 19.0];
        yield 'no VAT ID, export is exempt' => [[], 'CH', CartPrice::TAX_STATE_FREE, 8.1];
        yield 'no VAT ID, intra-EU supply uses destination tax' => [[], 'FR', CartPrice::TAX_STATE_GROSS, 20.0];
        yield 'no VAT ID, domestic supply uses seller tax' => [[], 'DE', CartPrice::TAX_STATE_GROSS, 19.0];
        yield 'invalid VAT ID, export is exempt' => [['DEINVALID'], 'CH', CartPrice::TAX_STATE_FREE, 8.1];
        yield 'invalid VAT ID, intra-EU supply uses destination tax' => [['DEINVALID'], 'FR', CartPrice::TAX_STATE_GROSS, 20.0];
        yield 'invalid VAT ID, domestic supply uses seller tax' => [['DEINVALID'], 'DE', CartPrice::TAX_STATE_GROSS, 19.0];
    }

    /**
     * @param list<string> $vatIds
     */
    #[DataProvider('multipleVatIdsContainingSellerVatIdProvider')]
    public function testAnySellerVatIdKeepsSellerTaxRule(array $vatIds, bool $checkVatIdPattern): void
    {
        static::getContainer()->get('country.repository')->update([[
            'id' => $this->countryIds['FR'],
            'checkVatIdPattern' => $checkVatIdPattern,
        ]], Context::createDefaultContext());
        $this->customerRepository->update([[
            'id' => $this->customerId,
            'vatIds' => $vatIds,
        ]], Context::createDefaultContext());

        $context = $this->createContext();

        static::assertSame(CartPrice::TAX_STATE_GROSS, $context->getTaxState());
        static::assertSame(19.0, $context->buildTaxRules($this->taxId)->first()?->getTaxRate());
        static::assertSame($vatIds, $context->getCustomer()?->getVatIds());
        static::assertSame($this->countryIds['FR'], $context->getShippingLocation()->getCountry()->getId());
        static::assertSame($this->shippingStateId, $context->getShippingLocation()->getState()?->getId());
        static::assertSame('75001', $context->getShippingLocation()->getAddress()?->getZipcode());
    }

    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function multipleVatIdsContainingSellerVatIdProvider(): iterable
    {
        yield 'another member state VAT ID does not hide the seller VAT ID' => [['NL123456789B01', 'DE123456789'], true];
        yield 'empty entries do not hide the seller VAT ID' => [['', 'DE123456789', 'NL123456789B01'], true];
        yield 'invalid entries do not hide the seller VAT ID' => [['INVALID', 'DE123456789'], true];
        yield 'unchecked foreign VAT ID does not hide the seller VAT ID' => [['NL123456789B01', 'DE123456789'], false];
    }

    private function createContext(): SalesChannelContext
    {
        static::getContainer()->get(VatIdPatternProvider::class)->reset();
        static::getContainer()->get(IntraCommunityTaxResolver::class)->reset();

        return static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [SalesChannelContextService::CUSTOMER_ID => $this->customerId]
        );
    }
}
