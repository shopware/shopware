<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader\EntityCollectionLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoaderConfigSerializer;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfigSerializer;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[CoversClass(EntityCollectionLoaderConfigSerializer::class)]
class EntityCollectionLoaderConfigSerializerTest extends TestCase
{
    private EntityLoaderConfigSerializer $delegate;

    private EntityCollectionLoaderConfigSerializer $serializer;

    protected function setUp(): void
    {
        $this->delegate = new EntityLoaderConfigSerializer();
        $this->serializer = new EntityCollectionLoaderConfigSerializer($this->delegate);
    }

    #[TestDox('returns entity_collection source identifier')]
    public function testGetSourceReturnsEntityCollectionString(): void
    {
        static::assertSame(EntityCollectionLoader::SOURCE, EntityCollectionLoaderConfigSerializer::getSource());
    }

    #[TestDox('decodes valid config array by delegating to EntityLoaderConfigSerializer and returns EntityLoaderConfig')]
    public function testDecodeValidConfigDelegatesToEntityLoaderConfigSerializer(): void
    {
        $data = [
            'entity' => 'product',
            'property' => 'productId',
        ];

        $result = $this->serializer->decode($data);

        static::assertInstanceOf(EntityLoaderConfig::class, $result);
        static::assertSame('product', $result->entity);
        static::assertSame('productId', $result->property);
        static::assertSame([], $result->associations);
    }

    #[TestDox('decodes config with associations by delegating to EntityLoaderConfigSerializer')]
    public function testDecodeWithAssociationsDelegatesToEntityLoaderConfigSerializer(): void
    {
        $data = [
            'entity' => 'category',
            'property' => 'categoryId',
            'associations' => ['children', 'seoUrls'],
        ];

        $result = $this->serializer->decode($data);

        static::assertInstanceOf(EntityLoaderConfig::class, $result);
        static::assertSame('category', $result->entity);
        static::assertSame('categoryId', $result->property);
        static::assertSame(['children', 'seoUrls'], $result->associations);
    }

    #[TestDox('encodes EntityLoaderConfig by delegating to EntityLoaderConfigSerializer and returns array')]
    public function testEncodeValidConfigDelegatesToEntityLoaderConfigSerializer(): void
    {
        $config = new EntityLoaderConfig('product', 'productId', []);

        $result = $this->serializer->encode($config);

        static::assertIsArray($result);
        static::assertSame('product', $result['entity']);
        static::assertSame('productId', $result['property']);
        static::assertArrayNotHasKey('associations', $result);
    }

    #[TestDox('encodes EntityLoaderConfig with associations by delegating to EntityLoaderConfigSerializer')]
    public function testEncodeWithAssociationsDelegatesToEntityLoaderConfigSerializer(): void
    {
        $config = new EntityLoaderConfig('category', 'categoryId', ['children', 'seoUrls']);

        $result = $this->serializer->encode($config);

        static::assertIsArray($result);
        static::assertSame([
            'entity' => 'category',
            'property' => 'categoryId',
            'associations' => ['children', 'seoUrls'],
        ], $result);
    }

    #[TestDox('decode and encode are inverse operations via delegation')]
    public function testDecodeAndEncodeAreInverseViaDelegation(): void
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

    #[TestDox('throws DecorationPatternException when getDecorated is called')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->expectExceptionMessage('The getDecorated() function of core class');

        $this->serializer->getDecorated();
    }

    #[TestDox('decode propagates exception from delegate when entity key is missing')]
    public function testDecodePropagatsDelegateExceptionForMissingEntity(): void
    {
        $this->expectException(\Shopware\Core\Content\ContentSystem\ContentSystemException::class);
        $this->expectExceptionMessage('Field entity expected non-empty string');

        $this->serializer->decode(['property' => 'productId']);
    }

    #[TestDox('decode propagates exception from delegate when property key is missing')]
    public function testDecodePropagatesDelegateExceptionForMissingProperty(): void
    {
        $this->expectException(\Shopware\Core\Content\ContentSystem\ContentSystemException::class);
        $this->expectExceptionMessage('Field property expected non-empty string');

        $this->serializer->decode(['entity' => 'product']);
    }

    #[TestDox('encode propagates exception from delegate when config type is wrong')]
    public function testEncodePropagatesDelegateExceptionForWrongConfigType(): void
    {
        $wrongConfig = new class extends AbstractContentDataLoaderConfig {
            public function getDecorated(): AbstractContentDataLoaderConfig
            {
                throw new DecorationPatternException(self::class);
            }
        };

        $this->expectException(\Shopware\Core\Content\ContentSystem\ContentSystemException::class);
        $this->expectExceptionMessage('Field config expected');

        $this->serializer->encode($wrongConfig);
    }
}
