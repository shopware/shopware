<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductReviewLoaderConfig;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductReviewLoaderConfigSerializer;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ProductReviewLoaderConfigSerializer::class)]
class ProductReviewLoaderConfigSerializerTest extends TestCase
{
    private ProductReviewLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ProductReviewLoaderConfigSerializer();
    }

    #[TestDox('returns product_review source identifier')]
    public function testGetSourceReturnsProductReviewString(): void
    {
        static::assertSame('product_review', ProductReviewLoaderConfigSerializer::getSource());
    }

    #[TestDox('decodes empty array into ProductReviewLoaderConfig with null property and null associationOverride')]
    public function testDecodeEmptyArrayReturnsProductReviewLoaderConfigWithNullProperty(): void
    {
        $result = $this->serializer->decode([]);

        static::assertInstanceOf(ProductReviewLoaderConfig::class, $result);
        static::assertNull($result->property);
        static::assertSame([], $result->associations);
        static::assertNull($result->associationOverride);
    }

    #[TestDox('decodes config with valid property into ProductReviewLoaderConfig with property set')]
    public function testDecodeWithValidPropertySetsProperty(): void
    {
        $result = $this->serializer->decode(['property' => 'myProperty']);

        static::assertInstanceOf(ProductReviewLoaderConfig::class, $result);
        static::assertSame('myProperty', $result->property);
        static::assertSame([], $result->associations);
    }

    #[TestDox('decodes config with valid associations into ProductReviewLoaderConfig with associations set')]
    public function testDecodeWithValidAssociationsSetsAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => ['customer', 'product']]);

        static::assertInstanceOf(ProductReviewLoaderConfig::class, $result);
        static::assertNull($result->property);
        static::assertSame(['customer', 'product'], $result->associations);
    }

    #[TestDox('decodes config with both property and associations into ProductReviewLoaderConfig with all values')]
    public function testDecodeWithAllFieldsReturnsProductReviewLoaderConfigWithAllValues(): void
    {
        $result = $this->serializer->decode([
            'property' => 'reviewProperty',
            'associations' => ['customer', 'product'],
        ]);

        static::assertInstanceOf(ProductReviewLoaderConfig::class, $result);
        static::assertSame('reviewProperty', $result->property);
        static::assertSame(['customer', 'product'], $result->associations);
    }

    #[TestDox('decodes null associations into ProductReviewLoaderConfig with empty associations')]
    public function testDecodeNullAssociationsReturnsEmptyAssociations(): void
    {
        $result = $this->serializer->decode(['associations' => null]);

        static::assertInstanceOf(ProductReviewLoaderConfig::class, $result);
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

        $this->serializer->decode(['associations' => 'customer']);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidAssociationItemProvider')]
    #[TestDox('throws exception when association item is invalid: $_dataName')]
    public function testDecodeWithInvalidAssociationItemThrowsException(array $data, string $field, string $actualType): void
    {
        $this->expectExceptionObject(
            ProductException::invalidFieldValueType($field, 'non-empty string', $actualType)
        );

        $this->serializer->decode($data);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidAssociationItemProvider(): iterable
    {
        yield 'empty string triggers empty guard' => [
            ['associations' => ['']], 'associations.0', 'string',
        ];
        yield 'non-zero index correctly reported in field path' => [
            ['associations' => ['customer', '']], 'associations.1', 'string',
        ];
        yield 'non-string type triggers type guard' => [
            ['associations' => [42]], 'associations.0', 'integer',
        ];
    }

    #[TestDox('encodes ProductReviewLoaderConfig with defaults into empty array')]
    public function testEncodeConfigWithDefaultsReturnsEmptyArray(): void
    {
        $config = new ProductReviewLoaderConfig();

        $result = $this->serializer->encode($config);

        static::assertSame([], $result);
    }

    #[TestDox('encodes ProductReviewLoaderConfig with property into array containing property key')]
    public function testEncodeConfigWithPropertyIncludesPropertyKey(): void
    {
        $config = new ProductReviewLoaderConfig(property: 'reviewProp');

        $result = $this->serializer->encode($config);

        static::assertSame(['property' => 'reviewProp'], $result);
    }

    #[TestDox('encodes ProductReviewLoaderConfig with associations into array containing associations key')]
    public function testEncodeConfigWithAssociationsIncludesAssociationsKey(): void
    {
        $config = new ProductReviewLoaderConfig(associations: ['customer', 'product']);

        $result = $this->serializer->encode($config);

        static::assertSame(['associations' => ['customer', 'product']], $result);
    }

    #[TestDox('encodes ProductReviewLoaderConfig with property and associations into full array')]
    public function testEncodeConfigWithAllFieldsReturnsFullArray(): void
    {
        $config = new ProductReviewLoaderConfig(
            property: 'reviewProp',
            associations: ['customer', 'product'],
        );

        $result = $this->serializer->encode($config);

        static::assertSame([
            'property' => 'reviewProp',
            'associations' => ['customer', 'product'],
        ], $result);
    }

    #[TestDox('decodes a valid associationOverride into the config')]
    public function testDecodeWithValidAssociationOverrideSetsAssociationOverride(): void
    {
        $result = $this->serializer->decode(['associationOverride' => 'extraAssociations']);

        static::assertInstanceOf(ProductReviewLoaderConfig::class, $result);
        static::assertSame('extraAssociations', $result->associationOverride);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[TestWithJson('[{"associationOverride": ""}, "string"]', 'associationOverride is empty string')]
    #[TestWithJson('[{"associationOverride": 42}, "integer"]', 'associationOverride is non-string type')]
    #[TestDox('throws exception when associationOverride is invalid')]
    public function testDecodeWithInvalidAssociationOverrideThrowsException(array $data, string $actualType): void
    {
        $this->expectExceptionObject(
            ProductException::invalidFieldValueType('associationOverride', 'non-empty string', $actualType)
        );

        $this->serializer->decode($data);
    }

    /**
     * @param array<string, mixed> $original
     */
    #[DataProvider('roundTripsProvider')]
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
    public static function roundTripsProvider(): iterable
    {
        yield 'empty config' => [[]];
        yield 'property only' => [['property' => 'reviewProperty']];
        yield 'associations only' => [['associations' => ['customer', 'product']]];
        yield 'association override only' => [['associationOverride' => 'extraAssociations']];
        yield 'full config' => [
            ['property' => 'myProperty', 'associations' => ['customer', 'product']],
        ];
    }

    #[TestDox('throws exception when encoding a non-ProductReviewLoaderConfig config instance')]
    public function testEncodeWithWrongConfigTypeThrowsException(): void
    {
        $this->expectExceptionObject(
            ProductException::invalidFieldValueType('config', ProductReviewLoaderConfig::class, StubLoaderConfig::class)
        );

        $this->serializer->encode(new StubLoaderConfig());
    }
}
