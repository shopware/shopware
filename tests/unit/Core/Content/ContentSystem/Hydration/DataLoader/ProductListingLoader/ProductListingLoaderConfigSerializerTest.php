<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader\ProductListingLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ProductListingLoader\ProductListingLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ProductListingLoader\ProductListingLoaderConfigSerializer;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
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

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('emptyOrNullAssociationsProvider')]
    #[TestDox('decodes absent or null associations into ProductListingLoaderConfig with empty associations')]
    public function testDecodeEmptyOrNullAssociationsReturnsEmptyAssociations(array $data): void
    {
        $result = $this->serializer->decode($data);

        static::assertInstanceOf(ProductListingLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function emptyOrNullAssociationsProvider(): array
    {
        return [
            'absent associations key' => [[]],
            'null associations value' => [['associations' => null]],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidPropertyProvider')]
    #[TestDox('throws exception when property is invalid')]
    public function testDecodeWithInvalidPropertyThrowsException(array $data): void
    {
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Field property expected non-empty string');

        $this->serializer->decode($data);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidPropertyProvider(): array
    {
        return [
            'property is empty string' => [['property' => '']],
            'property is non-string (integer)' => [['property' => 42]],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidAssociationsProvider')]
    #[TestDox('throws exception when associations is not an array')]
    public function testDecodeWithNonArrayAssociationsThrowsException(array $data): void
    {
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Field associations expected array');

        $this->serializer->decode($data);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidAssociationsProvider(): array
    {
        return [
            'associations is non-array (string)' => [['associations' => 'manufacturer']],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidAssociationItemProvider')]
    #[TestDox('throws exception when an association item is invalid')]
    public function testDecodeWithInvalidAssociationItemThrowsException(array $data, string $expectedMessagePart): void
    {
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage($expectedMessagePart);

        $this->serializer->decode($data);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidAssociationItemProvider(): array
    {
        return [
            'first item is empty string' => [
                ['associations' => ['']],
                'Field associations.0 expected non-empty string',
            ],
            'second item is empty string' => [
                ['associations' => ['manufacturer', '']],
                'Field associations.1 expected non-empty string',
            ],
            'first item is non-string (integer)' => [
                ['associations' => [42]],
                'Field associations.0 expected non-empty string',
            ],
        ];
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
        static::assertArrayNotHasKey('associations', $result);
    }

    #[TestDox('encodes ProductListingLoaderConfig with associations into array containing associations key')]
    public function testEncodeConfigWithAssociationsIncludesAssociationsKey(): void
    {
        $config = new ProductListingLoaderConfig(associations: ['media', 'options']);

        $result = $this->serializer->encode($config);

        static::assertSame(['associations' => ['media', 'options']], $result);
        static::assertArrayNotHasKey('property', $result);
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

    /**
     * @param array<string, mixed> $original
     */
    #[DataProvider('roundTripProvider')]
    #[TestDox('round-trips $description without data loss')]
    public function testDecodeAndEncodeAreInverse(array $original, string $description): void
    {
        $config = $this->serializer->decode($original);
        $encoded = $this->serializer->encode($config);

        static::assertSame($original, $encoded);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function roundTripProvider(): array
    {
        return [
            'empty config' => [[], 'empty config'],
            'property only' => [['property' => 'categoryProperty'], 'property-only config'],
            'associations only' => [['associations' => ['options', 'cover']], 'associations-only config'],
            'full config' => [
                ['property' => 'myProperty', 'associations' => ['manufacturer', 'media']],
                'full config',
            ],
        ];
    }

    #[TestDox('throws exception when encoding a non-ProductListingLoaderConfig config instance')]
    public function testEncodeWithWrongConfigTypeThrowsException(): void
    {
        $wrongConfig = new class extends AbstractContentDataLoaderConfig {
            public function getDecorated(): AbstractContentDataLoaderConfig
            {
                throw new DecorationPatternException(self::class);
            }
        };

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Field config expected');

        $this->serializer->encode($wrongConfig);
    }

    #[TestDox('throws DecorationPatternException when getDecorated is called')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->expectExceptionMessage('The getDecorated() function of core class');

        $this->serializer->getDecorated();
    }
}
