<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingAggregationsLoaderConfig;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingAggregationsLoaderConfigSerializer;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductListingAggregationsLoaderConfigSerializer::class)]
class ProductListingAggregationsLoaderConfigSerializerTest extends TestCase
{
    private ProductListingAggregationsLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ProductListingAggregationsLoaderConfigSerializer();
    }

    #[TestDox('returns product_listing_aggregations source identifier')]
    public function testGetSourceReturnsProductListingAggregationsString(): void
    {
        static::assertSame(
            'product_listing_aggregations',
            ProductListingAggregationsLoaderConfigSerializer::getSource()
        );
    }

    #[TestDox('decodes empty array into ProductListingAggregationsLoaderConfig with null property')]
    public function testDecodeEmptyArrayReturnsConfigWithNullProperty(): void
    {
        $result = $this->serializer->decode([]);

        static::assertInstanceOf(ProductListingAggregationsLoaderConfig::class, $result);
        static::assertNull($result->property);
    }

    #[TestDox('decodes config with valid property into ProductListingAggregationsLoaderConfig with property set')]
    public function testDecodeWithValidPropertySetsProperty(): void
    {
        $result = $this->serializer->decode(['property' => 'categoryId']);

        static::assertInstanceOf(ProductListingAggregationsLoaderConfig::class, $result);
        static::assertSame('categoryId', $result->property);
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

    #[TestDox('encodes ProductListingAggregationsLoaderConfig with defaults into empty array')]
    public function testEncodeConfigWithDefaultsReturnsEmptyArray(): void
    {
        static::assertSame([], $this->serializer->encode(new ProductListingAggregationsLoaderConfig()));
    }

    #[TestDox('encodes ProductListingAggregationsLoaderConfig with property into array containing property key')]
    public function testEncodeConfigWithPropertyIncludesPropertyKey(): void
    {
        $result = $this->serializer->encode(new ProductListingAggregationsLoaderConfig('categoryId'));

        static::assertSame(['property' => 'categoryId'], $result);
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
    }

    #[TestDox('throws exception when encoding a non-ProductListingAggregationsLoaderConfig config instance')]
    public function testEncodeWithWrongConfigTypeThrowsException(): void
    {
        $this->expectExceptionObject(
            ProductException::invalidFieldValueType(
                'config',
                ProductListingAggregationsLoaderConfig::class,
                StubLoaderConfig::class
            )
        );

        $this->serializer->encode(new StubLoaderConfig());
    }
}
