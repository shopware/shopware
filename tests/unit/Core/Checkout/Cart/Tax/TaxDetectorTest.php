<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Tax;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
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
        // Without the setting the shop cannot tell a domestic supply from an intra-community one, so
        // the fallback stays off and only the delivery country's own pattern counts, exactly as it did
        // before the fallback existed
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

    /**
     * @param array<string, string> $euPatterns ISO code => VAT ID format pattern
     * @param string|null $sellerIso the member state the shop supplies from, null when it configured none
     */
    private function createDetector(array $euPatterns = [], ?string $sellerIso = null): TaxDetector
    {
        $rows = [];
        $countryIds = [];
        foreach ($euPatterns as $iso => $pattern) {
            $countryIds[$iso] = Uuid::randomHex();
            $rows[] = ['iso' => $iso, 'id' => $countryIds[$iso], 'vat_id_pattern' => $pattern];
        }

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService->method('getString')->willReturn($sellerIso === null ? '' : $countryIds[$sellerIso]);

        return new TaxDetector(new VatIdPatternProvider($connection, $systemConfigService));
    }

    private function createDetectorRejectingQueries(): TaxDetector
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');

        return new TaxDetector(new VatIdPatternProvider($connection, static::createStub(SystemConfigService::class)));
    }
}
