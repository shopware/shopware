<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWithJson;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfigSerializer;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[CoversClass(EntityLoaderConfigSerializer::class)]
class EntityLoaderConfigSerializerTest extends TestCase
{
    private EntityLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new EntityLoaderConfigSerializer();
    }

    #[TestDox('returns entity source identifier')]
    public function testGetSourceReturnsEntityString(): void
    {
        static::assertSame('entity', EntityLoaderConfigSerializer::getSource());
    }

    #[TestDox('decodes valid config array with entity and property into EntityLoaderConfig')]
    public function testDecodeValidConfigReturnsEntityLoaderConfig(): void
    {
        $result = $this->serializer->decode([
            'entity' => 'product',
            'property' => 'productId',
        ]);

        static::assertInstanceOf(EntityLoaderConfig::class, $result);
        static::assertSame('product', $result->entity);
        static::assertSame('productId', $result->property);
        static::assertSame([], $result->associations);
    }

    #[TestDox('decodes config with associations list into EntityLoaderConfig')]
    public function testDecodeWithAssociationsReturnsEntityLoaderConfigWithAssociations(): void
    {
        $result = $this->serializer->decode([
            'entity' => 'product',
            'property' => 'productId',
            'associations' => ['manufacturer', 'cover'],
        ]);

        static::assertInstanceOf(EntityLoaderConfig::class, $result);
        static::assertSame(['manufacturer', 'cover'], $result->associations);
    }

    #[TestDox('decodes config with null associations into EntityLoaderConfig with empty associations')]
    public function testDecodeWithNullAssociationsReturnsEmptyAssociations(): void
    {
        $result = $this->serializer->decode([
            'entity' => 'product',
            'property' => 'productId',
            'associations' => null,
        ]);

        static::assertInstanceOf(EntityLoaderConfig::class, $result);
        static::assertSame([], $result->associations);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[TestWithJson('[{"property": "productId"}, "NULL"]', 'missing entity key')]
    #[TestWithJson('[{"entity": "", "property": "productId"}, "string"]', 'entity is empty string')]
    #[TestWithJson('[{"entity": 42, "property": "productId"}, "integer"]', 'entity is non-string type')]
    #[TestDox('throws exception when entity key is missing or invalid')]
    public function testDecodeMissingOrInvalidEntityThrowsException(array $data, string $actualType): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('entity', 'non-empty string', $actualType)
        );

        $this->serializer->decode($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[TestWithJson('[{"entity": "product"}, "NULL"]', 'missing property key')]
    #[TestWithJson('[{"entity": "product", "property": ""}, "string"]', 'property is empty string')]
    #[TestWithJson('[{"entity": "product", "property": 42}, "integer"]', 'property is non-string type')]
    #[TestDox('throws exception when property key is missing or invalid')]
    public function testDecodeMissingOrInvalidPropertyThrowsException(array $data, string $actualType): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('property', 'non-empty string', $actualType)
        );

        $this->serializer->decode($data);
    }

    #[TestDox('throws exception when associations is not an array')]
    public function testDecodeInvalidAssociationsTypeThrowsException(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('associations', 'array', 'string')
        );

        $this->serializer->decode([
            'entity' => 'product',
            'property' => 'productId',
            'associations' => 'manufacturer',
        ]);
    }

    #[TestDox('throws exception when an association entry is an integer')]
    public function testDecodeAssociationEntryIntegerThrowsException(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('associations.0', 'non-empty string', 'integer')
        );

        $this->serializer->decode([
            'entity' => 'product',
            'property' => 'productId',
            'associations' => [42],
        ]);
    }

    #[TestDox('throws exception when an association entry is an empty string')]
    public function testDecodeAssociationEntryEmptyStringThrowsException(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('associations.0', 'non-empty string', 'string')
        );

        $this->serializer->decode([
            'entity' => 'product',
            'property' => 'productId',
            'associations' => [''],
        ]);
    }

    #[TestDox('encodes EntityLoaderConfig without associations into array without associations key')]
    public function testEncodeWithoutAssociationsOmitsAssociationsKey(): void
    {
        $config = new EntityLoaderConfig('product', 'productId', []);

        $result = $this->serializer->encode($config);

        static::assertSame(['entity' => 'product', 'property' => 'productId'], $result);
    }

    #[TestDox('encodes EntityLoaderConfig with associations into array including associations key')]
    public function testEncodeWithAssociationsIncludesAssociationsKey(): void
    {
        $config = new EntityLoaderConfig('product', 'productId', ['manufacturer', 'cover']);

        $result = $this->serializer->encode($config);

        static::assertSame([
            'entity' => 'product',
            'property' => 'productId',
            'associations' => ['manufacturer', 'cover'],
        ], $result);
    }

    #[TestDox('throws exception when encoding a non-EntityLoaderConfig config instance')]
    public function testEncodeWithWrongConfigTypeThrowsException(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('config', EntityLoaderConfig::class, StubLoaderConfig::class)
        );

        $this->serializer->encode(new StubLoaderConfig());
    }

    #[TestDox('decode and encode are inverse operations for a valid config')]
    public function testDecodeAndEncodeAreInverse(): void
    {
        $original = [
            'entity' => 'product',
            'property' => 'productId',
            'associations' => ['manufacturer', 'cover'],
        ];

        $config = $this->serializer->decode($original);
        $encoded = $this->serializer->encode($config);

        static::assertSame($original, $encoded);
    }

    #[TestDox('decode and encode are inverse for config without associations')]
    public function testDecodeAndEncodeAreInverseWithoutAssociations(): void
    {
        $original = [
            'entity' => 'product',
            'property' => 'productId',
        ];

        $config = $this->serializer->decode($original);
        $encoded = $this->serializer->encode($config);

        static::assertSame($original, $encoded);
    }

    #[TestDox('throws DecorationPatternException when getDecorated is called')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(EntityLoaderConfigSerializer::class));

        $this->serializer->getDecorated();
    }
}
