<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Breadcrumb\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Breadcrumb\BreadcrumbException;
use Shopware\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbLoaderConfig;
use Shopware\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbLoaderConfigSerializer;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[CoversClass(BreadcrumbLoaderConfigSerializer::class)]
class BreadcrumbLoaderConfigSerializerTest extends TestCase
{
    private BreadcrumbLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new BreadcrumbLoaderConfigSerializer();
    }

    #[TestDox('returns breadcrumb source identifier')]
    public function testGetSourceReturnsBreadcrumbString(): void
    {
        static::assertSame('breadcrumb', BreadcrumbLoaderConfigSerializer::getSource());
    }

    #[TestDox('decodes empty array into BreadcrumbLoaderConfig with defaults')]
    public function testDecodeEmptyArrayReturnsBreadcrumbLoaderConfigWithDefaults(): void
    {
        $result = $this->serializer->decode([]);

        static::assertInstanceOf(BreadcrumbLoaderConfig::class, $result);
        static::assertNull($result->property);
        static::assertSame('product', $result->type);
        static::assertNull($result->referrerCategoryProperty);
    }

    #[TestDox('decodes config with valid property into BreadcrumbLoaderConfig with property set')]
    public function testDecodeWithValidPropertySetsProperty(): void
    {
        $result = $this->serializer->decode(['property' => 'myProperty']);

        static::assertInstanceOf(BreadcrumbLoaderConfig::class, $result);
        static::assertSame('myProperty', $result->property);
    }

    #[TestDox('decodes config with valid type into BreadcrumbLoaderConfig with type set')]
    public function testDecodeWithValidTypeSetsType(): void
    {
        $result = $this->serializer->decode(['type' => 'category']);

        static::assertInstanceOf(BreadcrumbLoaderConfig::class, $result);
        static::assertSame('category', $result->type);
    }

    #[TestDox('decodes config with valid referrerCategoryProperty into BreadcrumbLoaderConfig')]
    public function testDecodeWithValidReferrerCategoryPropertySetsValue(): void
    {
        $result = $this->serializer->decode(['referrerCategoryProperty' => 'referrerCategory']);

        static::assertInstanceOf(BreadcrumbLoaderConfig::class, $result);
        static::assertSame('referrerCategory', $result->referrerCategoryProperty);
    }

    #[TestDox('decodes config with all fields into BreadcrumbLoaderConfig with all values')]
    public function testDecodeWithAllFieldsReturnsBreadcrumbLoaderConfigWithAllValues(): void
    {
        $result = $this->serializer->decode([
            'property' => 'entityProp',
            'type' => 'category',
            'referrerCategoryProperty' => 'referrerCat',
        ]);

        static::assertInstanceOf(BreadcrumbLoaderConfig::class, $result);
        static::assertSame('entityProp', $result->property);
        static::assertSame('category', $result->type);
        static::assertSame('referrerCat', $result->referrerCategoryProperty);
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
            BreadcrumbException::invalidFieldValueType('property', 'non-empty string', $actualType)
        );

        $this->serializer->decode($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[TestWithJson('[{"type": ""}, "string"]', 'type is empty string')]
    #[TestWithJson('[{"type": 42}, "integer"]', 'type is non-string type')]
    #[TestDox('throws exception when type is invalid')]
    public function testDecodeWithInvalidTypeThrowsException(array $data, string $actualType): void
    {
        $this->expectExceptionObject(
            BreadcrumbException::invalidFieldValueType('type', 'non-empty string', $actualType)
        );

        $this->serializer->decode($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[TestWithJson('[{"referrerCategoryProperty": ""}, "string"]', 'referrerCategoryProperty is empty string')]
    #[TestWithJson('[{"referrerCategoryProperty": 42}, "integer"]', 'referrerCategoryProperty is non-string type')]
    #[TestDox('throws exception when referrerCategoryProperty is invalid')]
    public function testDecodeWithInvalidReferrerCategoryPropertyThrowsException(array $data, string $actualType): void
    {
        $this->expectExceptionObject(
            BreadcrumbException::invalidFieldValueType('referrerCategoryProperty', 'non-empty string', $actualType)
        );

        $this->serializer->decode($data);
    }

    #[TestDox('encodes BreadcrumbLoaderConfig with defaults into empty array')]
    public function testEncodeConfigWithDefaultsReturnsEmptyArray(): void
    {
        $config = new BreadcrumbLoaderConfig();

        $result = $this->serializer->encode($config);

        static::assertSame([], $result);
    }

    #[TestDox('encodes BreadcrumbLoaderConfig with property into array containing property key')]
    public function testEncodeConfigWithPropertyIncludesPropertyKey(): void
    {
        $config = new BreadcrumbLoaderConfig(property: 'entityProp');

        $result = $this->serializer->encode($config);

        static::assertSame(['property' => 'entityProp'], $result);
    }

    #[TestDox('encodes BreadcrumbLoaderConfig with non-default type into array containing type key')]
    public function testEncodeConfigWithNonDefaultTypeIncludesTypeKey(): void
    {
        $config = new BreadcrumbLoaderConfig(type: 'category');

        $result = $this->serializer->encode($config);

        static::assertSame(['type' => 'category'], $result);
    }

    #[TestDox('encodes BreadcrumbLoaderConfig with referrerCategoryProperty into array')]
    public function testEncodeConfigWithReferrerCategoryPropertyIncludesKey(): void
    {
        $config = new BreadcrumbLoaderConfig(referrerCategoryProperty: 'referrerCat');

        $result = $this->serializer->encode($config);

        static::assertSame(['referrerCategoryProperty' => 'referrerCat'], $result);
    }

    #[TestDox('encodes BreadcrumbLoaderConfig with all fields into full array')]
    public function testEncodeConfigWithAllFieldsReturnsFullArray(): void
    {
        $config = new BreadcrumbLoaderConfig(
            property: 'entityProp',
            type: 'category',
            referrerCategoryProperty: 'referrerCat',
        );

        $result = $this->serializer->encode($config);

        static::assertSame([
            'property' => 'entityProp',
            'type' => 'category',
            'referrerCategoryProperty' => 'referrerCat',
        ], $result);
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
        yield 'property only' => [['property' => 'entityProp']];
        yield 'type only' => [['type' => 'category']];
        yield 'referrerCategoryProperty only' => [['referrerCategoryProperty' => 'referrerCat']];
        yield 'full config' => [
            ['property' => 'myEntity', 'type' => 'category', 'referrerCategoryProperty' => 'referrerCat'],
        ];
    }

    #[TestDox('throws exception when encoding a non-BreadcrumbLoaderConfig config instance')]
    public function testEncodeWithWrongConfigTypeThrowsException(): void
    {
        $this->expectExceptionObject(
            BreadcrumbException::invalidFieldValueType('config', BreadcrumbLoaderConfig::class, StubLoaderConfig::class)
        );

        $this->serializer->encode(new StubLoaderConfig());
    }
}
