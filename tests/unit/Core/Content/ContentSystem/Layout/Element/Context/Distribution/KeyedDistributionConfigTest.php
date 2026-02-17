<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(KeyedDistributionConfig::class)]
class KeyedDistributionConfigTest extends TestCase
{
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

    #[TestDox('returns null for consumer whose properties lack the key property')]
    public function testDistributeReturnsNullWhenConsumerLacksKeyProperty(): void
    {
        $config = KeyedDistributionConfig::simple();

        $data = ['product-1' => ['name' => 'Product One']];

        $consumers = [
            ['component' => 'ProductCard', 'properties' => []],
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

    #[TestDox('returns null for consumer when data_key property value is not a string or int')]
    public function testDistributeReturnsNullWhenDataKeyIsNotStringOrInt(): void
    {
        $config = KeyedDistributionConfig::simple();

        $data = ['product-1' => ['name' => 'Product One']];

        $consumers = [
            ['component' => 'ProductCard', 'properties' => ['data_key' => ['not-scalar']]],
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
        static::assertSame(DistributionStrategy::Keyed, $config->getStrategy());
        static::assertSame('my-alias', $config->getConsumerAlias());
    }
}
