<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\Transformer\AddressTransformer;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AddressTransformer::class)]
class AddressTransformerTest extends TestCase
{
    public function testTransformOmitsEmptyFields(): void
    {
        $transformed = AddressTransformer::transform($this->createCustomerAddress());

        static::assertArrayNotHasKey('company', $transformed);
        static::assertArrayNotHasKey('department', $transformed);
        static::assertArrayNotHasKey('countryStateId', $transformed);
        static::assertArrayHasKey('street', $transformed);
        static::assertSame('Musterstreet 1', $transformed['street']);
    }

    public function testTransformForUpdateResetsEmptyOptionalFields(): void
    {
        $transformed = AddressTransformer::transformForUpdate($this->createCustomerAddress());

        foreach ([
            'company',
            'department',
            'salutationId',
            'title',
            'zipcode',
            'phoneNumber',
            'additionalAddressLine1',
            'additionalAddressLine2',
            'countryStateId',
            'customFields',
        ] as $field) {
            static::assertArrayHasKey($field, $transformed);
            static::assertNull($transformed[$field], \sprintf('Field "%s" should be reset to null', $field));
        }

        static::assertSame('Max', $transformed['firstName']);
        static::assertSame('Mustermann', $transformed['lastName']);
        static::assertSame('Musterstreet 1', $transformed['street']);
        static::assertSame('Musterstadt', $transformed['city']);
        static::assertTrue(Uuid::isValid($transformed['id']));
    }

    public function testTransformForUpdateKeepsFilledOptionalFields(): void
    {
        $address = $this->createCustomerAddress();
        $address->setCompany('shopware AG');
        $address->setDepartment('Development');
        $address->setZipcode('48624');
        $address->setCustomFields(['foo' => 'bar']);

        $transformed = AddressTransformer::transformForUpdate($address);

        static::assertSame('shopware AG', $transformed['company']);
        static::assertSame('Development', $transformed['department']);
        static::assertSame('48624', $transformed['zipcode']);
        static::assertSame(['foo' => 'bar'], $transformed['customFields']);
        static::assertNull($transformed['title']);
    }

    private function createCustomerAddress(): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setId(Uuid::randomHex());
        $address->setFirstName('Max');
        $address->setLastName('Mustermann');
        $address->setStreet('Musterstreet 1');
        $address->setCity('Musterstadt');
        $address->setCountryId(Uuid::randomHex());

        return $address;
    }
}
