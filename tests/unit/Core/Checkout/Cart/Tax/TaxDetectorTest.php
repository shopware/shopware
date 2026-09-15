<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Tax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(TaxDetector::class)]
class TaxDetectorTest extends TestCase
{
    private const EU_PATTERNS = [
        'BE' => 'BE\d{10}',
        'DE' => 'DE\d{9}',
        'NL' => 'NL\d{9}B\d{2}',
    ];

    public function testIsCompanyTaxFreeWithEuCountryAndValidVatIdMatchingPattern(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['DE123456789'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector();
        static::assertTrue($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeWithEuCountryAndInvalidVatIdPattern(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['INVALID-VAT'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $detector = $this->createDetector();

        $this->expectExceptionObject(new DecorationPatternException(TaxDetector::class));

        $detector->getDecorated();
    }

    public function testGetTaxStateReturnsFreeWhenNetDelivery(): void
    {
        $country = (new CountryEntity())->assign([
            'customerTax' => new TaxFreeConfig(true),
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getShippingLocation')->willReturn(ShippingLocation::createFromCountry($country));

        $detector = $this->createDetector();
        static::assertSame(CartPrice::TAX_STATE_FREE, $detector->getTaxState($context));
    }

    public function testGetTaxStateReturnsGrossWhenNotNetDeliveryAndUseGross(): void
    {
        $country = (new CountryEntity())->assign([
            'customerTax' => new TaxFreeConfig(false),
            'companyTax' => new TaxFreeConfig(false),
        ]);

        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setDisplayGross(true);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getShippingLocation')->willReturn(ShippingLocation::createFromCountry($country));
        $context->method('getCurrentCustomerGroup')->willReturn($customerGroup);

        $detector = $this->createDetector();
        static::assertSame(CartPrice::TAX_STATE_GROSS, $detector->getTaxState($context));
    }

    public function testGetTaxStateReturnsNetWhenNotNetDeliveryAndNotUseGross(): void
    {
        $country = (new CountryEntity())->assign([
            'customerTax' => new TaxFreeConfig(false),
            'companyTax' => new TaxFreeConfig(false),
        ]);

        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setDisplayGross(false);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getShippingLocation')->willReturn(ShippingLocation::createFromCountry($country));
        $context->method('getCurrentCustomerGroup')->willReturn($customerGroup);

        $detector = $this->createDetector();
        static::assertSame(CartPrice::TAX_STATE_NET, $detector->getTaxState($context));
    }

    public function testIsCompanyTaxFreeReturnsTrueWhenNonEuCountry(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => false,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector();
        static::assertTrue($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeReturnsFalseWhenCustomerIsNull(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => false,
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);

        $detector = $this->createDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeReturnsFalseWhenEuCountryAndEmptyVatIds(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => [],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeReturnsFalseWhenCustomerIsNotABusinessAccount(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => false,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeReturnsFalseForAPrivateAccountThatCarriesACompanyName(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'NL\d{9}B\d{2}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'company' => 'Acme BV',
            'vatIds' => ['NL123456789B01'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeReturnsTrueForABusinessAccountWithoutACompanyName(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'NL\d{9}B\d{2}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => null,
            'vatIds' => ['NL123456789B01'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector();
        static::assertTrue($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeReturnsFalseWhenCountryCompanyTaxDisabled(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(false),
            'isEu' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['DE123456789'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeWithEuCountryAndMultipleValidVatIdsMatchingPattern(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['DE123456789', 'DE987654321'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector();
        static::assertTrue($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeWithEuCountryAndMultipleVatIdsOneInvalidReturnsFalse(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['DE123456789', 'INVALID'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeWithEuCountryAndVatIdOfOtherMemberState(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'BE\d{10}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['NL123456789B01'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getSalesChannelId')->willReturn(Uuid::randomHex());

        $detector = $this->createDetector(self::EU_PATTERNS, 'DE');
        static::assertTrue($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeReturnsFalseWhenVatIdMatchesNoMemberState(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'BE\d{10}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['CHE123456789'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector(self::EU_PATTERNS);
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeReturnsFalseWhenOneOfMultipleVatIdsMatchesNoMemberState(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'BE\d{10}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['NL123456789B01', 'INVALID'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector(self::EU_PATTERNS);
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeWithEuCountryAndPatternThatDoesNotCompile(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'BE[0-9',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['NL123456789B01'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getSalesChannelId')->willReturn(Uuid::randomHex());

        $detector = $this->createDetector(self::EU_PATTERNS, 'DE');
        static::assertTrue($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeDoesNotLoadEuPatternsWhenCustomerIsNotABusinessAccount(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'BE\d{10}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'vatIds' => ['NL123456789B01'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetectorRejectingQueries();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeDoesNotLoadEuPatternsWhenNonEuCountry(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => false,
            'vatIdPattern' => 'CHE\d{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['NL123456789B01'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetectorRejectingQueries();
        static::assertTrue($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeDoesNotLoadEuPatternsWhenAllVatIdsMatchPattern(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'NL\d{9}B\d{2}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['NL123456789B01', 'NL987654321B02'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetectorRejectingQueries();
        static::assertTrue($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeReturnsFalseForAVatIdOfTheSellersOwnMemberState(): void
    {
        // A German shop delivering to Belgium for a customer identified in Germany: the customer holds a
        // VAT ID of the seller's own member state, which Article 138 does not exempt
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'BE\d{10}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['DE123456789'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getSalesChannelId')->willReturn(Uuid::randomHex());

        static::assertFalse($this->createDetector(self::EU_PATTERNS, 'DE')->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeStillAcceptsAnotherMemberStateWhenTheSellerCountryIsConfigured(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'BE\d{10}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['NL123456789B01'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getSalesChannelId')->willReturn(Uuid::randomHex());

        static::assertTrue($this->createDetector(self::EU_PATTERNS, 'DE')->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeRejectsEveryOtherMemberStateWhileNoSellerCountryIsConfigured(): void
    {
        // Without the setting a domestic supply is indistinguishable from an intra-community one, so
        // only the delivery country's own pattern counts
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'BE\d{10}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['DE123456789'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getSalesChannelId')->willReturn(Uuid::randomHex());

        static::assertFalse($this->createDetector(self::EU_PATTERNS)->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeStillAcceptsTheDeliveryCountrysOwnPatternWhileNoSellerCountryIsConfigured(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'BE\d{10}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['BE0123456789'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getSalesChannelId')->willReturn(Uuid::randomHex());

        static::assertTrue($this->createDetector(self::EU_PATTERNS)->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeRejectsADomesticDeliveryWithAVatIdOfAnotherMemberState(): void
    {
        // A German shop delivering inside Germany: the goods never cross a border, so Article 138 does not exempt.
        $country = (new CountryEntity())->assign([
            'iso' => 'DE',
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'DE\d{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['BE0123456789'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getSalesChannelId')->willReturn(Uuid::randomHex());

        static::assertFalse($this->createDetector(self::EU_PATTERNS, 'DE')->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeRejectsADomesticDeliveryWithTheSellerStatesOwnVatId(): void
    {
        $country = (new CountryEntity())->assign([
            'iso' => 'DE',
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'DE\d{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['DE123456789'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getSalesChannelId')->willReturn(Uuid::randomHex());

        static::assertFalse($this->createDetector(self::EU_PATTERNS, 'DE')->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeRejectsADomesticDeliveryWhileTheVatIdPatternCheckIsOff(): void
    {
        // The pattern check only decides which VAT IDs count, never whether the supply crosses a border
        $country = (new CountryEntity())->assign([
            'iso' => 'DE',
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'DE\d{9}',
            'checkVatIdPattern' => false,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['BE0123456789'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getSalesChannelId')->willReturn(Uuid::randomHex());

        static::assertFalse($this->createDetector(self::EU_PATTERNS, 'DE')->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeAcceptsADeliveryLeavingTheSellerState(): void
    {
        // The same customer and seller as the domestic case, only the goods now cross a border
        $country = (new CountryEntity())->assign([
            'iso' => 'FR',
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'FR\w{2}\d{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['BE0123456789'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getSalesChannelId')->willReturn(Uuid::randomHex());

        static::assertTrue($this->createDetector(self::EU_PATTERNS, 'DE')->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeAcceptsADomesticDeliveryWhileNoSellerCountryIsConfigured(): void
    {
        // Without the setting the shop cannot tell a domestic supply apart.
        $country = (new CountryEntity())->assign([
            'iso' => 'DE',
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'DE\d{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => ['DE123456789'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getSalesChannelId')->willReturn(Uuid::randomHex());

        static::assertTrue($this->createDetector(self::EU_PATTERNS)->isCompanyTaxFree($context, $country));
    }

    /**
     * @param array<string, string> $euPatterns ISO code => VAT ID format pattern
     * @param string|null $sellerIso the member state the shop supplies from, null when it configured none
     */
    private function createDetector(array $euPatterns = [], ?string $sellerIso = null): TaxDetector
    {
        $countries = new CountryCollection();
        foreach ($euPatterns as $iso => $pattern) {
            $country = new CountryEntity();
            $country->setId(Uuid::randomHex());
            $country->setIso($iso);
            $country->setVatIdPattern($pattern);
            $country->setIsEu(true);
            $countries->add($country);
        }

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            static fn (Criteria $criteria, Context $context) => new EntitySearchResult(CountryDefinition::ENTITY_NAME, $countries->count(), $countries, null, $criteria, $context)
        );

        $sellerCountryId = $sellerIso === null ? '' : $countries->filterByProperty('iso', $sellerIso)->first()?->getId();

        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getString')->willReturn($sellerCountryId ?? '');

        return new TaxDetector(new VatIdPatternProvider($repository, $systemConfigService));
    }

    private function createDetectorRejectingQueries(): TaxDetector
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('search');

        return new TaxDetector(new VatIdPatternProvider($repository, static::createStub(SystemConfigService::class)));
    }
}
