<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Aggregate\CustomerAddress;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerAddressEntity::class)]
class CustomerAddressEntityTest extends TestCase
{
    #[DataProvider('zipcodeProvider')]
    #[TestDox('setZipcode normalizes empty values to null')]
    public function testSetZipcodeNormalizesEmptyToNull(?string $input, ?string $expected): void
    {
        $address = new CustomerAddressEntity();
        $address->setZipcode($input);

        static::assertSame($expected, $address->getZipcode());
    }

    /**
     * @return \Generator<string, array{0: ?string, 1: ?string}>
     */
    public static function zipcodeProvider(): \Generator
    {
        yield 'null stays null' => [null, null];
        yield 'empty string becomes null' => ['', null];
        yield 'non-empty value is kept' => ['12345', '12345'];
        yield 'zero string is kept' => ['0', '0'];
    }

    public function testNameGettersAndSetters(): void
    {
        $address = new CustomerAddressEntity();
        $address->setFirstName('Jane');
        $address->setLastName('Doe');
        $address->setCity('Berlin');
        $address->setStreet('Main Street 1');

        static::assertSame('Jane', $address->getFirstName());
        static::assertSame('Doe', $address->getLastName());
        static::assertSame('Berlin', $address->getCity());
        static::assertSame('Main Street 1', $address->getStreet());
    }

    public function testOptionalFieldsDefaultToNull(): void
    {
        $address = new CustomerAddressEntity();

        static::assertNull($address->getCompany());
        static::assertNull($address->getDepartment());
        static::assertNull($address->getPhoneNumber());
        static::assertNull($address->getHash());
    }
}
