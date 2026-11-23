<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\ProspectiveAddress;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ProspectiveAddress::class)]
class ProspectiveAddressTest extends TestCase
{
    /**
     * @param array<string, mixed>|null $expected
     */
    #[DataProvider('payloadProvider')]
    public function testFromPayload(mixed $address, ?array $expected): void
    {
        static::assertSame($expected, ProspectiveAddress::fromPayload($address)?->toPayload());
    }

    public static function payloadProvider(): \Generator
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

    public function testThePayloadContainsEveryField(): void
    {
        static::assertSame(ProspectiveAddress::FIELDS, array_keys((new ProspectiveAddress('country-id'))->toPayload()));
    }
}
