<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityCacheTagResolver::class)]
class EntityCacheTagResolverTest extends TestCase
{
    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function knownEntityProvider(): \Generator
    {
        yield 'product' => ['product', 'product-abc123'];
        yield 'category' => ['category', 'category-route-abc123'];
        yield 'landing page' => ['landing_page', 'landing-page-route-abc123'];
        yield 'cms page' => ['cms_page', 'cms-page-abc123'];
        yield 'product stream' => ['product_stream', 'product-stream-abc123'];
    }

    #[DataProvider('knownEntityProvider')]
    #[TestDox('resolves $entityName to cache tag with correct prefix')]
    public function testResolvesKnownEntityTypesToCacheTags(string $entityName, string $expectedTag): void
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $resolver = new EntityCacheTagResolver();

        static::assertSame($expectedTag, $resolver->resolve($definition, 'abc123'));
    }

    #[TestDox('returns null for unsupported entity type')]
    public function testReturnsNullForUnsupportedEntity(): void
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('order');

        $resolver = new EntityCacheTagResolver();

        static::assertNull($resolver->resolve($definition, 'abc123'));
    }

    /**
     * @return \Generator<string, array{Entity, list<string>}>
     */
    public static function relatedTagProvider(): \Generator
    {
        yield 'product with a parent and a manufacturer' => [
            self::product('parent-1', 'manufacturer-1'),
            ['product-parent-1', 'product-manufacturer-manufacturer-1'],
        ];
        yield 'product with only a parent' => [
            self::product('parent-1', null),
            ['product-parent-1'],
        ];
        yield 'product with only a manufacturer' => [
            self::product(null, 'manufacturer-1'),
            ['product-manufacturer-manufacturer-1'],
        ];
        yield 'product without a parent or a manufacturer' => [
            self::product(null, null),
            [],
        ];
        yield 'sales-channel product resolves the same tags as its base product' => [
            self::salesChannelProduct('parent-1', 'manufacturer-1'),
            ['product-parent-1', 'product-manufacturer-manufacturer-1'],
        ];
        yield 'non-product entity has no related tags' => [
            new Entity(),
            [],
        ];
    }

    /**
     * @param list<string> $expectedTags
     */
    #[DataProvider('relatedTagProvider')]
    #[TestDox('resolves the related cache tags of a loaded entity')]
    public function testResolvesRelatedTags(Entity $entity, array $expectedTags): void
    {
        static::assertSame($expectedTags, (new EntityCacheTagResolver())->resolveRelated($entity));
    }

    private static function product(?string $parentId, ?string $manufacturerId): ProductEntity
    {
        $product = new ProductEntity();
        $product->setParentId($parentId);
        $product->setManufacturerId($manufacturerId);

        return $product;
    }

    private static function salesChannelProduct(?string $parentId, ?string $manufacturerId): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setParentId($parentId);
        $product->setManufacturerId($manufacturerId);

        return $product;
    }
}
