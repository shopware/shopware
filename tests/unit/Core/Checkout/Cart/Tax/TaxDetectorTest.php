<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Tax;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
            'company' => 'EU Company',
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
            'company' => 'EU Company',
            'vatIds' => ['INVALID-VAT'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeWithEuCountryAndVatIdWithTrailingNewline(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'company' => 'EU Company',
            'vatIds' => ["DE123456789\n"],
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

    /**
     * @param list<string> $vatIds
     */
    #[DataProvider('netDeliveryProvider')]
    public function testIsNetDeliveryAppliesTheTaxFreeSettingOfTheAccountType(
        ?string $accountType,
        bool $customerTaxFree,
        bool $companyTaxFree,
        bool $isEu,
        array $vatIds,
        bool $expected,
    ): void {
        $country = (new CountryEntity())->assign([
            'customerTax' => new TaxFreeConfig($customerTaxFree),
            'companyTax' => new TaxFreeConfig($companyTaxFree),
            'isEu' => $isEu,
        ]);

        $customer = $accountType === null ? null : (new CustomerEntity())->assign([
            'accountType' => $accountType,
            'company' => 'Acme AG',
            'vatIds' => $vatIds,
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getShippingLocation')->willReturn(ShippingLocation::createFromCountry($country));
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector();
        static::assertSame($expected, $detector->isNetDelivery($context));
    }

    public static function netDeliveryProvider(): \Generator
    {
        yield 'business account with VAT ID in EU country with B2B tax-free is tax-free' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'customerTaxFree' => false,
            'companyTaxFree' => true,
            'isEu' => true,
            'vatIds' => ['DE123456789'],
            'expected' => true,
        ];

        yield 'business account without VAT ID in EU country with B2B tax-free is taxed' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'customerTaxFree' => false,
            'companyTaxFree' => true,
            'isEu' => true,
            'vatIds' => [],
            'expected' => false,
        ];

        yield 'business account with VAT ID in non-EU country with B2B tax-free is tax-free' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'customerTaxFree' => false,
            'companyTaxFree' => true,
            'isEu' => false,
            'vatIds' => ['CHE123456789'],
            'expected' => true,
        ];

        yield 'business account without VAT ID in non-EU country with B2B tax-free is tax-free' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'customerTaxFree' => false,
            'companyTaxFree' => true,
            'isEu' => false,
            'vatIds' => [],
            'expected' => true,
        ];

        yield 'business account with VAT ID in EU country with only B2C tax-free is taxed' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'customerTaxFree' => true,
            'companyTaxFree' => false,
            'isEu' => true,
            'vatIds' => ['DE123456789'],
            'expected' => false,
        ];

        yield 'business account without VAT ID in EU country with only B2C tax-free is taxed' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'customerTaxFree' => true,
            'companyTaxFree' => false,
            'isEu' => true,
            'vatIds' => [],
            'expected' => false,
        ];

        yield 'business account without VAT ID in non-EU country with only B2C tax-free is taxed' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'customerTaxFree' => true,
            'companyTaxFree' => false,
            'isEu' => false,
            'vatIds' => [],
            'expected' => false,
        ];

        yield 'business account with VAT ID in EU country with B2C and B2B tax-free is tax-free' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'customerTaxFree' => true,
            'companyTaxFree' => true,
            'isEu' => true,
            'vatIds' => ['DE123456789'],
            'expected' => true,
        ];

        yield 'business account without VAT ID in EU country with B2C and B2B tax-free is taxed' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'customerTaxFree' => true,
            'companyTaxFree' => true,
            'isEu' => true,
            'vatIds' => [],
            'expected' => false,
        ];

        yield 'business account without VAT ID in non-EU country with B2C and B2B tax-free is tax-free' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'customerTaxFree' => true,
            'companyTaxFree' => true,
            'isEu' => false,
            'vatIds' => [],
            'expected' => true,
        ];

        yield 'business account with VAT ID in country without tax-free settings is taxed' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'customerTaxFree' => false,
            'companyTaxFree' => false,
            'isEu' => true,
            'vatIds' => ['DE123456789'],
            'expected' => false,
        ];

        yield 'private account in EU country with B2C tax-free is tax-free' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'customerTaxFree' => true,
            'companyTaxFree' => false,
            'isEu' => true,
            'vatIds' => [],
            'expected' => true,
        ];

        yield 'private account in non-EU country with B2C tax-free is tax-free' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'customerTaxFree' => true,
            'companyTaxFree' => false,
            'isEu' => false,
            'vatIds' => [],
            'expected' => true,
        ];

        yield 'private account with company and VAT ID in EU country with only B2B tax-free is taxed' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'customerTaxFree' => false,
            'companyTaxFree' => true,
            'isEu' => true,
            'vatIds' => ['DE123456789'],
            'expected' => false,
        ];

        yield 'private account with company and VAT ID in non-EU country with only B2B tax-free is taxed' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'customerTaxFree' => false,
            'companyTaxFree' => true,
            'isEu' => false,
            'vatIds' => ['CHE123456789'],
            'expected' => false,
        ];

        yield 'private account with company in non-EU country with only B2B tax-free is taxed' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'customerTaxFree' => false,
            'companyTaxFree' => true,
            'isEu' => false,
            'vatIds' => [],
            'expected' => false,
        ];

        yield 'private account with company and VAT ID in EU country with B2C and B2B tax-free is tax-free' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'customerTaxFree' => true,
            'companyTaxFree' => true,
            'isEu' => true,
            'vatIds' => ['DE123456789'],
            'expected' => true,
        ];

        yield 'private account with company and VAT ID in country without tax-free settings is taxed' => [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'customerTaxFree' => false,
            'companyTaxFree' => false,
            'isEu' => true,
            'vatIds' => ['DE123456789'],
            'expected' => false,
        ];

        yield 'no customer in country with B2C tax-free is tax-free' => [
            'accountType' => null,
            'customerTaxFree' => true,
            'companyTaxFree' => false,
            'isEu' => true,
            'vatIds' => [],
            'expected' => true,
        ];

        yield 'no customer in country with only B2B tax-free is taxed' => [
            'accountType' => null,
            'customerTaxFree' => false,
            'companyTaxFree' => true,
            'isEu' => false,
            'vatIds' => [],
            'expected' => false,
        ];
    }

    public function testIsCompanyTaxFreeReturnsTrueWhenNonEuCountry(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => false,
        ]);

        $customer = (new CustomerEntity())->assign([
            'company' => 'Non-EU Company',
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
            'company' => 'EU Company',
            'vatIds' => [],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeReturnsFalseWhenCustomerHasNoCompany(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => false,
        ]);

        $customer = (new CustomerEntity())->assign([
            'company' => null,
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector();
        static::assertFalse($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeReturnsFalseWhenCountryCompanyTaxDisabled(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(false),
            'isEu' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'company' => 'Test Company',
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
            'company' => 'EU Company',
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
            'company' => 'EU Company',
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
            'company' => 'EU Company',
            'vatIds' => ['NL123456789B01'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector(self::EU_PATTERNS);
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
            'company' => 'Swiss Company',
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
            'company' => 'EU Company',
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
            'company' => 'EU Company',
            'vatIds' => ['NL123456789B01'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetector(self::EU_PATTERNS);
        static::assertTrue($detector->isCompanyTaxFree($context, $country));
    }

    public function testIsCompanyTaxFreeDoesNotLoadEuPatternsWhenCustomerHasNoCompany(): void
    {
        $country = (new CountryEntity())->assign([
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => 'BE\d{10}',
            'checkVatIdPattern' => true,
        ]);

        $customer = (new CustomerEntity())->assign([
            'company' => null,
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
            'company' => 'EU Company',
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
            'company' => 'EU Company',
            'vatIds' => ['NL123456789B01', 'NL987654321B02'],
        ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);

        $detector = $this->createDetectorRejectingQueries();
        static::assertTrue($detector->isCompanyTaxFree($context, $country));
    }

    /**
     * @param array<string, string> $euPatterns ISO code => VAT ID format pattern
     */
    private function createDetector(array $euPatterns = []): TaxDetector
    {
        $rows = [];
        foreach ($euPatterns as $iso => $pattern) {
            $rows[] = ['iso' => $iso, 'id' => Uuid::randomHex(), 'vat_id_pattern' => $pattern];
        }

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        return new TaxDetector(new VatIdPatternProvider($connection));
    }

    private function createDetectorRejectingQueries(): TaxDetector
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');

        return new TaxDetector(new VatIdPatternProvider($connection));
    }
}
