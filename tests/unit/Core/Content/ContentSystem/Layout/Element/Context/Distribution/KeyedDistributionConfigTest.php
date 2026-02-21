<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;

/**
 * @internal
 */
#[CoversClass(KeyedDistributionConfig::class)]
class KeyedDistributionConfigTest extends TestCase
{
    /**
     * @return \Generator<string, array{array<mixed>}>
     */
    public static function absentOrNonScalarDataKeyProvider(): \Generator
    {
        yield 'consumer lacks key property' => [[]];
        yield 'data_key is non-scalar (array)' => [['data_key' => ['not-scalar']]];
    }

    #[TestDox('distributes provider data to consumer matching via data_key property')]
    public function testDistributeMatchesConsumerDataKeyToProviderData(): void
    {
        $config = KeyedDistributionConfig::simple();

        $data = [
            'product-1' => ['name' => 'Product One'],
            'product-2' => ['name' => 'Product Two'],
        ];

        $consumers = [
            ['component' => 'ProductCard', 'properties' => ['data_key' => 'product-1']],
            ['component' => 'ProductCard', 'properties' => ['data_key' => 'product-2']],
        ];

        $result = $config->distribute($data, $consumers);

        static::assertSame([['name' => 'Product One'], ['name' => 'Product Two']], $result);
    }

    /**
     * @param array<mixed> $consumerProperties
     */
    #[DataProvider('absentOrNonScalarDataKeyProvider')]
    #[TestDox('returns null when data key is absent or non-scalar')]
    public function testDistributeReturnsNullWhenDataKeyIsAbsentOrNonScalar(array $consumerProperties): void
    {
        $config = KeyedDistributionConfig::simple();

        $data = ['product-1' => ['name' => 'Product One']];

        $consumers = [
            ['component' => 'ProductCard', 'properties' => $consumerProperties],
        ];

        $result = $config->distribute($data, $consumers);

        static::assertSame([null], $result);
    }

    #[TestDox('returns null for every consumer when data is not an array')]
    public function testDistributeReturnsNullWhenDataIsNotArray(): void
    {
        $config = KeyedDistributionConfig::simple();

        $consumers = [
            ['component' => 'ProductCard', 'properties' => ['data_key' => 'product-1']],
            ['component' => 'ProductCard', 'properties' => ['data_key' => 'product-2']],
        ];

        $result = $config->distribute('not-an-array', $consumers);

        static::assertSame([null, null], $result);
    }

    #[TestDox('returns null for consumer when provider data lacks the matching key')]
    public function testDistributeReturnsNullWhenProviderDataLacksMatchingKey(): void
    {
        $config = KeyedDistributionConfig::simple();

        $data = ['product-1' => ['name' => 'Product One']];

        $consumers = [
            ['component' => 'ProductCard', 'properties' => ['data_key' => 'product-99']],
        ];

        $result = $config->distribute($data, $consumers);

        static::assertSame([null], $result);
    }

    #[TestDox('survives a fromArray to toArray roundtrip preserving all fields')]
    public function testFromArrayToArrayRoundtrip(): void
    {
        $original = [
            'distribution' => 'keyed',
            'key_property' => 'product_id',
            'consumer_alias' => 'my-alias',
        ];

        $config = KeyedDistributionConfig::fromArray($original);

        static::assertSame($original, $config->toArray());
    }
}
