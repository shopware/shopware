<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
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

    #[TestDox('returns constraint mapping with key_property NotBlank+Type and consumer_alias Type constraints')]
    public function testBuildConstraintsReturnsExpectedConstraints(): void
    {
        $constraints = KeyedDistributionConfig::buildConstraints();

        static::assertArrayHasKey('key_property', $constraints);
        static::assertCount(2, $constraints['key_property']);
        static::assertInstanceOf(NotBlank::class, $constraints['key_property'][0]);
        static::assertInstanceOf(Type::class, $constraints['key_property'][1]);
        static::assertSame('string', $constraints['key_property'][1]->type);

        static::assertArrayHasKey('consumer_alias', $constraints);
        static::assertCount(1, $constraints['consumer_alias']);
        static::assertInstanceOf(Type::class, $constraints['consumer_alias'][0]);
        static::assertSame('string', $constraints['consumer_alias'][0]->type);
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

    #[TestDox('returns null when consumer entry is not an array')]
    public function testDistributeReturnsNullWhenConsumerIsNotArray(): void
    {
        $config = KeyedDistributionConfig::simple();

        $data = ['product-1' => ['name' => 'Product One']];

        // @phpstan-ignore argument.type (intentionally passing non-array consumer to test the guard branch)
        $result = $config->distribute($data, ['not-an-array-consumer']);

        static::assertSame([null], $result);
    }

    #[TestDox('returns null when consumer properties value is not an array')]
    public function testDistributeReturnsNullWhenConsumerPropertiesIsNotArray(): void
    {
        $config = KeyedDistributionConfig::simple();

        $data = ['product-1' => ['name' => 'Product One']];

        $consumers = [
            ['component' => 'ProductCard', 'properties' => 'not-an-array'],
        ];

        // @phpstan-ignore argument.type (intentionally passing string as properties to test the guard branch)
        $result = $config->distribute($data, $consumers);

        static::assertSame([null], $result);
    }

    #[TestDox('returns null when data key is absent from consumer properties')]
    public function testDistributeReturnsNullWhenDataKeyIsAbsent(): void
    {
        $config = KeyedDistributionConfig::simple();

        $data = ['product-1' => ['name' => 'Product One']];

        $consumers = [
            ['component' => 'ProductCard', 'properties' => []],
        ];

        $result = $config->distribute($data, $consumers);

        static::assertSame([null], $result);
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
}
