<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\ProspectiveCustomer;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ProspectiveCustomer::class)]
class ProspectiveCustomerTest extends TestCase
{
    /**
     * @param array<string, mixed>|null $expected
     */
    #[DataProvider('addressProvider')]
    public function testNormalizeAddress(mixed $address, ?array $expected): void
    {
        static::assertSame($expected, ProspectiveCustomer::normalizeAddress($address));
    }

    public static function addressProvider(): \Generator
    {
        yield 'every field is set, so a stored address is replaced instead of merged' => [
            ['countryId' => 'country-id', 'zipcode' => '48624'],
            ['countryId' => 'country-id', 'countryStateId' => null, 'zipcode' => '48624', 'city' => null, 'street' => null],
        ];

        yield 'empty strings are stored as missing values' => [
            ['countryId' => 'country-id', 'countryStateId' => '', 'zipcode' => '', 'city' => 'Schöppingen', 'street' => ''],
            ['countryId' => 'country-id', 'countryStateId' => null, 'zipcode' => null, 'city' => 'Schöppingen', 'street' => null],
        ];

        yield 'unknown fields are dropped' => [
            ['countryId' => 'country-id', 'firstName' => 'Max', 'city' => 12],
            ['countryId' => 'country-id', 'countryStateId' => null, 'zipcode' => null, 'city' => null, 'street' => null],
        ];

        yield 'an address without a country is no address' => [['zipcode' => '48624'], null];
        yield 'an empty country is no address' => [['countryId' => ''], null];
        yield 'null is no address' => [null, null];
    }

    public function testTheRuleCustomerIsNotSerialized(): void
    {
        $address = ['countryId' => 'country-id', 'countryStateId' => null, 'zipcode' => '48624', 'city' => null, 'street' => null];

        $serialized = (new ProspectiveCustomer($address, null, new CustomerEntity()))->jsonSerialize();

        static::assertArrayNotHasKey('ruleCustomer', $serialized);
        static::assertSame($address, $serialized['shippingAddress']);
        static::assertNull($serialized['billingAddress']);
    }
}
