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
    public function testTransformLeavesOutEmptyFields(): void
    {
        $address = $this->createAddress();
        $address->setCompany('');

        $transformed = AddressTransformer::transform($address);

        static::assertArrayNotHasKey('company', $transformed);
        static::assertArrayNotHasKey('department', $transformed);
        static::assertArrayNotHasKey('customFields', $transformed);
        static::assertSame('Musterstraße 1', $transformed['street'] ?? null);
    }

    public function testTransformForUpdateSetsEmptyOptionalFieldsToNull(): void
    {
        $address = $this->createAddress();
        $address->setCompany('');

        $transformed = AddressTransformer::transformForUpdate($address);

        static::assertNull($transformed['company']);
        static::assertNull($transformed['department']);
        static::assertNull($transformed['salutationId']);
        static::assertNull($transformed['title']);
        static::assertNull($transformed['zipcode']);
        static::assertNull($transformed['phoneNumber']);
        static::assertNull($transformed['additionalAddressLine1']);
        static::assertNull($transformed['additionalAddressLine2']);
        static::assertNull($transformed['countryStateId']);
        static::assertSame('Max', $transformed['firstName']);
        static::assertSame('Musterstraße 1', $transformed['street']);
        static::assertArrayNotHasKey('customFields', $transformed);
    }

    public function testTransformForUpdateKeepsFilledFields(): void
    {
        $address = $this->createAddress();
        $address->setCompany('shopware AG');
        $address->setDepartment('Development');
        $address->setSalutationId(Uuid::randomHex());
        $address->setTitle('Dr.');
        $address->setZipcode('48624');
        $address->setPhoneNumber('0123456789');
        $address->setAdditionalAddressLine1('Building 2');
        $address->setAdditionalAddressLine2('Floor 3');
        $address->setCountryStateId(Uuid::randomHex());
        $address->setCustomFields(['foo' => 'bar']);

        $transformed = AddressTransformer::transformForUpdate($address);
        unset($transformed['id']);
        ksort($transformed);

        $expected = AddressTransformer::transform($address);
        unset($expected['id']);
        ksort($expected);

        static::assertSame($expected, $transformed);
    }

    private function createAddress(): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setId(Uuid::randomHex());
        $address->setFirstName('Max');
        $address->setLastName('Mustermann');
        $address->setStreet('Musterstraße 1');
        $address->setCity('Musterstadt');
        $address->setCountryId(Uuid::randomHex());

        return $address;
    }
}
