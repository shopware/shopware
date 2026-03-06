<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\CrossSellingLoaderConfig;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\CrossSellingLoaderConfigSerializer;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[CoversClass(CrossSellingLoaderConfigSerializer::class)]
class CrossSellingLoaderConfigSerializerTest extends TestCase
{
    private CrossSellingLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new CrossSellingLoaderConfigSerializer();
    }

    #[TestDox('returns cross_selling source identifier')]
    public function testGetSourceReturnsCrossSellingString(): void
    {
        static::assertSame('cross_selling', CrossSellingLoaderConfigSerializer::getSource());
    }

    #[TestDox('decodes empty array into CrossSellingLoaderConfig with null property')]
    public function testDecodeEmptyArrayReturnsCrossSellingLoaderConfigWithNullProperty(): void
    {
        $result = $this->serializer->decode([]);

        static::assertInstanceOf(CrossSellingLoaderConfig::class, $result);
        static::assertNull($result->property);
        static::assertSame([], $result->associations);
    }

    #[TestDox('decodes config with valid property into CrossSellingLoaderConfig with property set')]
    public function testDecodeWithValidPropertySetsProperty(): void
    {
        $result = $this->serializer->decode(['property' => 'myProperty']);

        static::assertInstanceOf(CrossSellingLoaderConfig::class, $result);
        static::assertSame('myProperty', $result->property);
        static::assertSame([], $result->associations);
    }

    #[TestDox('decodes config with valid associations into CrossSellingLoaderConfig with associations set')]
    public function testDecodeWithValidAssociationsSetsAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => ['manufacturer', 'categories']]);

        static::assertInstanceOf(CrossSellingLoaderConfig::class, $result);
        static::assertNull($result->property);
        static::assertSame(['manufacturer', 'categories'], $result->associations);
    }

    #[TestDox('decodes config with both property and associations into CrossSellingLoaderConfig with all values')]
    public function testDecodeWithAllFieldsReturnsCrossSellingLoaderConfigWithAllValues(): void
    {
        $result = $this->serializer->decode([
            'property' => 'crossSellingProperty',
            'associations' => ['media', 'options'],
        ]);

        static::assertInstanceOf(CrossSellingLoaderConfig::class, $result);
        static::assertSame('crossSellingProperty', $result->property);
        static::assertSame(['media', 'options'], $result->associations);
    }

    #[TestDox('decodes null associations into CrossSellingLoaderConfig with empty associations')]
    public function testDecodeNullAssociationsReturnsEmptyAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => null]);

        static::assertInstanceOf(CrossSellingLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[TestWithJson('[{"property": ""}, "string"]', 'property is empty string')]
    #[TestWithJson('[{"property": 42}, "integer"]', 'property is non-string type')]
    #[TestDox('throws exception when property is invalid')]
    public function testDecodeWithInvalidPropertyThrowsException(array $data, string $actualType): void
    {
        $this->expectExceptionObject(
            ProductException::invalidFieldValueType('property', 'non-empty string', $actualType)
        );

        $this->serializer->decode($data);
    }

    #[TestDox('throws exception when associations is not an array')]
    public function testDecodeWithNonArrayAssociationsThrowsException(): void
    {
        $this->expectExceptionObject(
            ProductException::invalidFieldValueType('associations', 'array', 'string')
        );

        $this->serializer->decode(['associations' => 'manufacturer']);
    }

    #[TestDox('throws exception when first association item is an empty string')]
    public function testDecodeWithEmptyStringFirstAssociationItemThrowsException(): void
    {
        $this->expectExceptionObject(
            ProductException::invalidFieldValueType('associations.0', 'non-empty string', 'string')
        );

        $this->serializer->decode(['associations' => ['']]);
    }

    #[TestDox('throws exception when second association item is an empty string')]
    public function testDecodeWithEmptyStringSecondAssociationItemThrowsException(): void
    {
        $this->expectExceptionObject(
            ProductException::invalidFieldValueType('associations.1', 'non-empty string', 'string')
        );

        $this->serializer->decode(['associations' => ['manufacturer', '']]);
    }

    #[TestDox('throws exception when first association item is a non-string type')]
    public function testDecodeWithNonStringFirstAssociationItemThrowsException(): void
    {
        $this->expectExceptionObject(
            ProductException::invalidFieldValueType('associations.0', 'non-empty string', 'integer')
        );

        $this->serializer->decode(['associations' => [42]]);
    }

    #[TestDox('encodes CrossSellingLoaderConfig with defaults into empty array')]
    public function testEncodeConfigWithDefaultsReturnsEmptyArray(): void
    {
        $config = new CrossSellingLoaderConfig();

        $result = $this->serializer->encode($config);

        static::assertSame([], $result);
    }

    #[TestDox('encodes CrossSellingLoaderConfig with property into array containing property key')]
    public function testEncodeConfigWithPropertyIncludesPropertyKey(): void
    {
        $config = new CrossSellingLoaderConfig(property: 'crossSellingProp');

        $result = $this->serializer->encode($config);

        static::assertSame(['property' => 'crossSellingProp'], $result);
    }

    #[TestDox('encodes CrossSellingLoaderConfig with associations into array containing associations key')]
    public function testEncodeConfigWithAssociationsIncludesAssociationsKey(): void
    {
        $config = new CrossSellingLoaderConfig(associations: ['media', 'options']);

        $result = $this->serializer->encode($config);

        static::assertSame(['associations' => ['media', 'options']], $result);
    }

    #[TestDox('encodes CrossSellingLoaderConfig with property and associations into full array')]
    public function testEncodeConfigWithAllFieldsReturnsFullArray(): void
    {
        $config = new CrossSellingLoaderConfig(
            property: 'crossSellingProp',
            associations: ['manufacturer', 'categories'],
        );

        $result = $this->serializer->encode($config);

        static::assertSame([
            'property' => 'crossSellingProp',
            'associations' => ['manufacturer', 'categories'],
        ], $result);
    }

    /**
     * @param array<string, mixed> $original
     */
    #[DataProvider('roundTripProvider')]
    #[TestDox('round-trips $_dataName without data loss')]
    public function testDecodeAndEncodeAreInverse(array $original): void
    {
        $config = $this->serializer->decode($original);
        $encoded = $this->serializer->encode($config);

        static::assertSame($original, $encoded);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function roundTripProvider(): iterable
    {
        yield 'empty config' => [[]];
        yield 'property only' => [['property' => 'productProperty']];
        yield 'associations only' => [['associations' => ['options', 'cover']]];
        yield 'full config' => [
            ['property' => 'myProperty', 'associations' => ['manufacturer', 'media']],
        ];
    }

    #[TestDox('throws exception when encoding a non-CrossSellingLoaderConfig config instance')]
    public function testEncodeWithWrongConfigTypeThrowsException(): void
    {
        $this->expectExceptionObject(
            ProductException::invalidFieldValueType('config', CrossSellingLoaderConfig::class, StubLoaderConfig::class)
        );

        $this->serializer->encode(new StubLoaderConfig());
    }
}
