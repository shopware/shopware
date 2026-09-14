<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Aggregate\CustomerAddress;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressNameFormatter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerAddressNameFormatter::class)]
class CustomerAddressNameFormatterTest extends TestCase
{
    #[DataProvider('nameProvider')]
    public function testItNamesTheAddress(string $firstName, string $lastName, ?string $company, string $expected): void
    {
        $address = new CustomerAddressEntity();
        $address->setFirstName($firstName);
        $address->setLastName($lastName);
        $address->setCompany($company);

        static::assertSame($expected, CustomerAddressNameFormatter::displayName($address));
    }

    /**
     * @return \Generator<string, array{string, string, string|null, string}>
     */
    public static function nameProvider(): \Generator
    {
        yield 'the person name' => ['Max', 'Mustermann', null, 'Max Mustermann'];

        yield 'the company is not appended to a person' => ['Max', 'Mustermann', 'Acme GmbH', 'Max Mustermann'];

        yield 'no person name falls back to the company' => ['', '', 'Acme GmbH', 'Acme GmbH'];

        yield 'a blank person name falls back to the company' => ['  ', ' ', 'Acme GmbH', 'Acme GmbH'];

        yield 'a first name alone is not padded' => ['Max', '', null, 'Max'];

        yield 'neither renders nothing' => ['', '', null, ''];

        yield 'a blank company renders nothing' => ['', '', '   ', ''];
    }
}
