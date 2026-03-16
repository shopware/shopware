<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\ContentSystem\Schema\AvailableDataResolver;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

#[CoversClass(AvailableDataResolver::class)]
class AvailableDataResolverTest extends TestCase
{
    #[TestDox('resolve passes non-wildcard entries through as-is')]
    public function testNonWildcardPassthrough(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);

        $resolver = new AvailableDataResolver($registry, [
            'navigation' => [['className' => Tree::class, 'genericParameters' => []]],
        ]);

        $map = $resolver->resolve();

        static::assertCount(1, $map->sourceToTypes['navigation']);
        static::assertSame(Tree::class, $map->sourceToTypes['navigation'][0]->className);
    }

    #[TestDox('resolve expands Entity wildcard using definitions')]
    public function testEntityWildcardExpansion(): void
    {
        $definition = $this->createMock(ProductDefinition::class);
        $definition->method('getEntityClass')->willReturn(ProductEntity::class);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([$definition]);

        $resolver = new AvailableDataResolver($registry, [
            'entity' => [['className' => Entity::class, 'genericParameters' => []]],
        ]);

        $map = $resolver->resolve();

        static::assertCount(1, $map->sourceToTypes['entity']);
        static::assertSame(ProductEntity::class, $map->sourceToTypes['entity'][0]->className);
    }

    #[TestDox('resolve expands EntityCollection wildcard using definitions')]
    public function testEntityCollectionWildcardExpansion(): void
    {
        $definition = $this->createMock(ProductDefinition::class);
        $definition->method('getCollectionClass')->willReturn(ProductCollection::class);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([$definition]);

        $resolver = new AvailableDataResolver($registry, [
            'entity_collection' => [['className' => EntityCollection::class, 'genericParameters' => []]],
        ]);

        $map = $resolver->resolve();

        static::assertCount(1, $map->sourceToTypes['entity_collection']);
        static::assertSame(ProductCollection::class, $map->sourceToTypes['entity_collection'][0]->className);
    }

    #[TestDox('resolve preserves genericParameters')]
    public function testGenericParametersPreserved(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);

        $resolver = new AvailableDataResolver($registry, [
            'product_review' => [['className' => 'EntitySearchResult', 'genericParameters' => ['ProductReviewCollection']]],
        ]);

        $map = $resolver->resolve();

        static::assertSame(['ProductReviewCollection'], $map->sourceToTypes['product_review'][0]->genericParameters);
    }
}
