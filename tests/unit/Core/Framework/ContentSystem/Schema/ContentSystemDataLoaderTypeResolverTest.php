<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeResolver;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * @internal
 */
#[CoversClass(ContentSystemDataLoaderTypeResolver::class)]
class ContentSystemDataLoaderTypeResolverTest extends TestCase
{
    #[TestDox('passes non-wildcard entries through as-is')]
    public function testNonWildcardPassthrough(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);

        $resolver = new ContentSystemDataLoaderTypeResolver($registry, [
            'navigation' => [['className' => Tree::class, 'genericParameters' => []]],
        ]);

        $map = $resolver->resolve();

        static::assertCount(1, $map->sourceToTypes['navigation']);
        static::assertSame(Tree::class, $map->sourceToTypes['navigation'][0]->className);
    }

    #[TestDox('expands Entity wildcard using definitions')]
    public function testEntityWildcardExpansion(): void
    {
        $definition = static::createStub(ProductDefinition::class);
        $definition->method('getEntityClass')->willReturn(ProductEntity::class);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([$definition]);

        $resolver = new ContentSystemDataLoaderTypeResolver($registry, [
            'entity' => [['className' => Entity::class, 'genericParameters' => []]],
        ]);

        $map = $resolver->resolve();

        static::assertCount(1, $map->sourceToTypes['entity']);
        static::assertSame(ProductEntity::class, $map->sourceToTypes['entity'][0]->className);
    }

    #[TestDox('expands EntityCollection wildcard using definitions')]
    public function testEntityCollectionWildcardExpansion(): void
    {
        $definition = static::createStub(ProductDefinition::class);
        $definition->method('getCollectionClass')->willReturn(ProductCollection::class);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([$definition]);

        $resolver = new ContentSystemDataLoaderTypeResolver($registry, [
            'entity_collection' => [['className' => EntityCollection::class, 'genericParameters' => []]],
        ]);

        $map = $resolver->resolve();

        static::assertCount(1, $map->sourceToTypes['entity_collection']);
        static::assertSame(ProductCollection::class, $map->sourceToTypes['entity_collection'][0]->className);
    }

    #[TestDox('preserves genericParameters')]
    public function testGenericParametersPreserved(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);

        $resolver = new ContentSystemDataLoaderTypeResolver($registry, [
            'product_review' => [['className' => EntitySearchResult::class, 'genericParameters' => [ProductReviewCollection::class]]],
        ]);

        $map = $resolver->resolve();

        static::assertSame([ProductReviewCollection::class], $map->sourceToTypes['product_review'][0]->genericParameters);
    }
}
