<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Context\Distribution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('framework')]
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
            'keyProperty' => 'product_id',
            'consumerAlias' => 'my-alias',
        ];

        $config = KeyedDistributionConfig::fromArray($original);

        static::assertSame($original, $config->toArray());
    }

    #[TestDox('falls back to the default keyProperty when the camelCase key is absent (legacy snake_case key_property is ignored)')]
    public function testFromArrayFallsBackToDefaultKeyPropertyWhenCamelCaseKeyAbsent(): void
    {
        $config = KeyedDistributionConfig::fromArray(['distribution' => 'keyed', 'key_property' => 'legacy-ignored']);

        static::assertSame(
            ['distribution' => 'keyed', 'keyProperty' => 'data_key', 'consumerAlias' => null],
            $config->toArray()
        );
    }

    /**
     * keyProperty rejects a present null via `array_key_exists` rather than `??`, so a present null is not
     * treated as absent-and-defaulted the way consumerAlias treats it; the null case and the non-string case
     * each guard a distinct regression (see {@see KeyedDistributionConfig::fromArray()}'s docblock), so both
     * stay even though today's code throws from the same `is_string` check.
     *
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidFieldDataProvider')]
    #[TestDox('rejects invalid field type instead of substituting the default: $_dataName')]
    public function testFromArrayRejectsInvalidFieldType(array $data, ContentSystemException $expected): void
    {
        $this->expectExceptionObject($expected);

        KeyedDistributionConfig::fromArray($data);
    }

    #[TestDox('returns constraint mapping with keyProperty NotBlank+Type and consumerAlias Type constraints')]
    public function testBuildConstraintsReturnsExpectedConstraints(): void
    {
        $constraints = KeyedDistributionConfig::buildConstraints();

        static::assertArrayHasKey('keyProperty', $constraints);
        static::assertCount(2, $constraints['keyProperty']);
        static::assertInstanceOf(NotBlank::class, $constraints['keyProperty'][0]);
        static::assertInstanceOf(Type::class, $constraints['keyProperty'][1]);
        static::assertSame('string', $constraints['keyProperty'][1]->type);

        static::assertArrayHasKey('consumerAlias', $constraints);
        static::assertCount(1, $constraints['consumerAlias']);
        static::assertInstanceOf(Type::class, $constraints['consumerAlias'][0]);
        static::assertSame('string', $constraints['consumerAlias'][0]->type);
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

    /**
     * @return iterable<string, array{array<string, mixed>, ContentSystemException}>
     */
    public static function invalidFieldDataProvider(): iterable
    {
        yield 'non-string keyProperty' => [
            ['distribution' => 'keyed', 'keyProperty' => 5],
            ContentSystemException::invalidFieldValueType('keyProperty', 'string', 'int'),
        ];

        yield 'null keyProperty' => [
            ['distribution' => 'keyed', 'keyProperty' => null],
            ContentSystemException::invalidFieldValueType('keyProperty', 'string', 'null'),
        ];

        yield 'non-string consumerAlias' => [
            ['distribution' => 'keyed', 'consumerAlias' => 42],
            ContentSystemException::invalidFieldValueType('consumerAlias', 'string', 'int'),
        ];
    }
}
