<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Address;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\VatIdInvalidError;
use Shopware\Core\Checkout\Cart\Address\Error\VatIdMissingError;
use Shopware\Core\Checkout\Cart\Address\VatIdValidator;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(VatIdValidator::class)]
class VatIdValidatorTest extends TestCase
{
    private VatIdValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new VatIdValidator();
    }

    /**
     * @param list<string>|null $vatIds
     * @param class-string<Error>|null $expectedError
     */
    #[DataProvider('customerProvider')]
    public function testValidate(
        string $accountType,
        bool $vatIdRequired,
        bool $checkVatIdPattern,
        ?array $vatIds,
        ?string $expectedError,
    ): void {
        $customer = $this->createCustomer($accountType, $vatIds);
        $customer->setActiveBillingAddress($this->createAddress($vatIdRequired, $checkVatIdPattern));

        $errors = $this->validate($customer);

        if ($expectedError === null) {
            static::assertCount(0, $errors);

            return;
        }

        static::assertCount(1, $errors);
        static::assertInstanceOf($expectedError, $errors->first());
    }

    public static function customerProvider(): \Generator
    {
        yield 'business customer without VAT ID in a country that requires one is blocked' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, true, false, null, VatIdMissingError::class,
        ];

        yield 'an empty VAT ID list counts as missing' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, true, false, [], VatIdMissingError::class,
        ];

        yield 'blank VAT IDs count as missing' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, true, false, [''], VatIdMissingError::class,
        ];

        yield 'business customer with a VAT ID passes' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, true, false, ['ATU12345678'], null,
        ];

        yield 'business customer in a country without the requirement passes' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, false, false, null, null,
        ];

        yield 'private customers never need a VAT ID' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE, true, true, null, null,
        ];

        yield 'a VAT ID matching the country pattern passes' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, true, true, ['ATU12345678'], null,
        ];

        yield 'a VAT ID not matching the country pattern is blocked' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, true, true, ['DE123456789'], VatIdInvalidError::class,
        ];

        yield 'every VAT ID has to match the country pattern' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, true, true, ['ATU12345678', 'DE123456789'], VatIdInvalidError::class,
        ];

        yield 'the pattern is anchored, so a matching part of the VAT ID is not enough' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, true, true, ['ATU123456789'], VatIdInvalidError::class,
        ];

        yield 'an optional VAT ID still has to match the country pattern' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, false, true, ['DE123456789'], VatIdInvalidError::class,
        ];

        yield 'the pattern is ignored when the country does not check it' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, true, false, ['DE123456789'], null,
        ];

        yield 'private customers are not checked against the pattern' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE, false, true, ['DE123456789'], null,
        ];
    }

    public function testCountryWithoutPatternAcceptsAnyVatId(): void
    {
        $address = $this->createAddress(vatIdRequired: true, checkVatIdPattern: true);
        $address->getCountry()?->setVatIdPattern('');

        $customer = $this->createCustomer(CustomerEntity::ACCOUNT_TYPE_BUSINESS, ['anything']);
        $customer->setActiveBillingAddress($address);

        static::assertCount(0, $this->validate($customer));
    }

    public function testCustomerWithoutActiveBillingAddressIsLeftToTheAddressValidator(): void
    {
        $errors = $this->validate($this->createCustomer(CustomerEntity::ACCOUNT_TYPE_BUSINESS, vatIds: null));

        static::assertCount(0, $errors);
    }

    public function testCartWithoutCustomerPasses(): void
    {
        $errors = new ErrorCollection();
        $this->validator->validate(new Cart('test'), $errors, Generator::generateSalesChannelContext(overrides: ['customer' => null]));

        static::assertCount(0, $errors);
    }

    private function validate(CustomerEntity $customer): ErrorCollection
    {
        $errors = new ErrorCollection();
        $this->validator->validate(new Cart('test'), $errors, Generator::generateSalesChannelContext(customer: $customer));

        return $errors;
    }

    /**
     * @param list<string>|null $vatIds
     */
    private function createCustomer(string $accountType, ?array $vatIds): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setAccountType($accountType);
        $customer->setVatIds($vatIds);

        return $customer;
    }

    private function createAddress(bool $vatIdRequired, bool $checkVatIdPattern): CustomerAddressEntity
    {
        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setVatIdRequired($vatIdRequired);
        $country->setCheckVatIdPattern($checkVatIdPattern);
        $country->setVatIdPattern('ATU\d{8}');

        $address = new CustomerAddressEntity();
        $address->setId(Uuid::randomHex());
        $address->setCountry($country);

        return $address;
    }
}
