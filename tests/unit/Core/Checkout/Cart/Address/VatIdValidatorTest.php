<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Address;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\VatIdMissingError;
use Shopware\Core\Checkout\Cart\Address\VatIdValidator;
use Shopware\Core\Checkout\Cart\Cart;
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
     */
    #[DataProvider('customerProvider')]
    public function testValidate(string $accountType, bool $vatIdRequired, ?array $vatIds, bool $expectError): void
    {
        $customer = $this->createCustomer($accountType, $vatIds);
        $customer->setActiveBillingAddress($this->createAddress($vatIdRequired));

        $errors = $this->validate($customer);

        if ($expectError) {
            static::assertCount(1, $errors);
            static::assertInstanceOf(VatIdMissingError::class, $errors->first());

            return;
        }

        static::assertCount(0, $errors);
    }

    public static function customerProvider(): \Generator
    {
        yield 'business customer without VAT ID in a country that requires one is blocked' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, true, null, true,
        ];

        yield 'an empty VAT ID list counts as missing' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, true, [], true,
        ];

        yield 'blank VAT IDs count as missing' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, true, [''], true,
        ];

        yield 'business customer with a VAT ID passes' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, true, ['ATU12345678'], false,
        ];

        yield 'business customer in a country without the requirement passes' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, false, null, false,
        ];

        yield 'private customers never need a VAT ID' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE, true, null, false,
        ];
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

    private function createAddress(bool $vatIdRequired): CustomerAddressEntity
    {
        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setVatIdRequired($vatIdRequired);

        $address = new CustomerAddressEntity();
        $address->setId(Uuid::randomHex());
        $address->setCountry($country);

        return $address;
    }
}
