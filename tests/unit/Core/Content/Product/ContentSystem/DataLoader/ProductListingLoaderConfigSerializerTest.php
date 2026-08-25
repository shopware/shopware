<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingLoaderConfig;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingLoaderConfigSerializer;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductListingLoaderConfigSerializer::class)]
class ProductListingLoaderConfigSerializerTest extends TestCase
{
    private ProductListingLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ProductListingLoaderConfigSerializer();
    }

    #[TestDox('returns product_listing source identifier')]
    public function testGetSourceReturnsProductListingString(): void
    {
        static::assertSame('product_listing', ProductListingLoaderConfigSerializer::getSource());
    }

    #[TestDox('decodes empty array into ProductListingLoaderConfig with null property')]
    public function testDecodeEmptyArrayReturnsProductListingLoaderConfigWithNullProperty(): void
    {
        $result = $this->serializer->decode([]);

        static::assertInstanceOf(ProductListingLoaderConfig::class, $result);
        static::assertNull($result->property);
    }

    #[TestDox('decodes config with valid property into ProductListingLoaderConfig with property set')]
    public function testDecodeWithValidPropertySetsProperty(): void
    {
        $result = $this->serializer->decode(['property' => 'myProperty']);

        static::assertInstanceOf(ProductListingLoaderConfig::class, $result);
        static::assertSame('myProperty', $result->property);
        static::assertSame([], $result->associations);
    }

    #[TestDox('decodes config with valid associations into ProductListingLoaderConfig with associations set')]
    public function testDecodeWithValidAssociationsSetsAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => ['manufacturer', 'categories']]);

        static::assertInstanceOf(ProductListingLoaderConfig::class, $result);
        static::assertNull($result->property);
        static::assertSame(['manufacturer', 'categories'], $result->associations);
    }

    #[TestDox('decodes config with both property and associations into ProductListingLoaderConfig with all values')]
    public function testDecodeWithAllFieldsReturnsProductListingLoaderConfigWithAllValues(): void
    {
        $result = $this->serializer->decode([
            'property' => 'listingProperty',
            'associations' => ['media', 'options'],
        ]);

        static::assertInstanceOf(ProductListingLoaderConfig::class, $result);
        static::assertSame('listingProperty', $result->property);
        static::assertSame(['media', 'options'], $result->associations);
    }

    #[TestDox('decodes null associations into ProductListingLoaderConfig with empty associations')]
    public function testDecodeNullAssociationsReturnsEmptyAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => null]);

        static::assertInstanceOf(ProductListingLoaderConfig::class, $result);
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

    #[TestDox('decodes the aggregations flag and defaults it to true')]
    public function testDecodeAggregationsFlag(): void
    {
        $default = $this->serializer->decode([]);
        $disabled = $this->serializer->decode(['aggregations' => false]);

        static::assertInstanceOf(ProductListingLoaderConfig::class, $default);
        static::assertInstanceOf(ProductListingLoaderConfig::class, $disabled);

        static::assertTrue($default->aggregations);
        static::assertFalse($disabled->aggregations);
    }

    #[TestDox('throws exception when the aggregations flag is not a bool')]
    public function testDecodeWithNonBoolAggregationsThrowsException(): void
    {
        $this->expectExceptionObject(
            ProductException::invalidFieldValueType('aggregations', 'bool', 'string')
        );

        $this->serializer->decode(['aggregations' => 'no']);
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

    #[TestDox('encodes ProductListingLoaderConfig with defaults into empty array')]
    public function testEncodeConfigWithDefaultsReturnsEmptyArray(): void
    {
        $config = new ProductListingLoaderConfig();

        $result = $this->serializer->encode($config);

        static::assertSame([], $result);
    }

    #[TestDox('encodes ProductListingLoaderConfig with property into array containing property key')]
    public function testEncodeConfigWithPropertyIncludesPropertyKey(): void
    {
        $config = new ProductListingLoaderConfig(property: 'listingProp');

        $result = $this->serializer->encode($config);

        static::assertSame(['property' => 'listingProp'], $result);
    }

    #[TestDox('encodes ProductListingLoaderConfig with associations into array containing associations key')]
    public function testEncodeConfigWithAssociationsIncludesAssociationsKey(): void
    {
        $config = new ProductListingLoaderConfig(associations: ['media', 'options']);

        $result = $this->serializer->encode($config);

        static::assertSame(['associations' => ['media', 'options']], $result);
    }

    #[TestDox('encodes ProductListingLoaderConfig with property and associations into full array')]
    public function testEncodeConfigWithAllFieldsReturnsFullArray(): void
    {
        $config = new ProductListingLoaderConfig(
            property: 'listingProp',
            associations: ['manufacturer', 'categories'],
        );

        $result = $this->serializer->encode($config);

        static::assertSame([
            'property' => 'listingProp',
            'associations' => ['manufacturer', 'categories'],
        ], $result);
    }

    #[TestDox('encodes ProductListingLoaderConfig with disabled aggregations into array containing aggregations key')]
    public function testEncodeConfigWithDisabledAggregationsIncludesAggregationsKey(): void
    {
        $config = new ProductListingLoaderConfig(aggregations: false);

        $result = $this->serializer->encode($config);

        static::assertSame(['aggregations' => false], $result);
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
        yield 'property only' => [['property' => 'categoryProperty']];
        yield 'associations only' => [['associations' => ['options', 'cover']]];
        yield 'aggregations disabled' => [['aggregations' => false]];
        yield 'full config' => [
            ['property' => 'myProperty', 'associations' => ['manufacturer', 'media']],
        ];
    }

    #[TestDox('throws exception when encoding a non-ProductListingLoaderConfig config instance')]
    public function testEncodeWithWrongConfigTypeThrowsException(): void
    {
        $this->expectExceptionObject(
            ProductException::invalidFieldValueType('config', ProductListingLoaderConfig::class, StubLoaderConfig::class)
        );

        $this->serializer->encode(new StubLoaderConfig());
    }
}
