<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentSystemDataLoaderTypeDescriptor;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypesResolvedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ContentSystemDataLoaderTypeResolver::class)]
class ContentSystemDataLoaderTypeResolverTest extends TestCase
{
    #[TestDox('preserves all descriptor fields when no subscriber is registered')]
    public function testPreservesDescriptorFieldsWithNoRegisteredSubscriber(): void
    {
        $resolver = new ContentSystemDataLoaderTypeResolver(
            ['product_review' => [['className' => EntitySearchResult::class, 'genericParameters' => [ProductReviewCollection::class]]]],
            new EventDispatcher(),
        );

        $map = $resolver->resolve();

        static::assertCount(1, $map->sourceToTypes['product_review']);
        static::assertSame(EntitySearchResult::class, $map->sourceToTypes['product_review'][0]->className);
        static::assertSame([ProductReviewCollection::class], $map->sourceToTypes['product_review'][0]->genericParameters);
    }

    #[TestDox('uses subscriber-provided types instead of compiled entries')]
    public function testSubscriberReplacesCompiledEntries(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ContentSystemDataLoaderTypesResolvedEvent::class . '.entity',
            static function (ContentSystemDataLoaderTypesResolvedEvent $event): void {
                $event->types = [new ContentSystemDataLoaderTypeDescriptor(ProductEntity::class)];
            },
        );

        $resolver = new ContentSystemDataLoaderTypeResolver(
            ['entity' => [['className' => Entity::class, 'genericParameters' => []]]],
            $dispatcher,
        );

        $map = $resolver->resolve();

        static::assertCount(1, $map->sourceToTypes['entity']);
        static::assertSame(ProductEntity::class, $map->sourceToTypes['entity'][0]->className);
    }

    #[TestDox('keeps compiled entries when no subscriber modifies types')]
    public function testKeepsCompiledEntriesWhenSubscriberIsNoOp(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ContentSystemDataLoaderTypesResolvedEvent::class . '.navigation',
            static function (ContentSystemDataLoaderTypesResolvedEvent $event): void {
                // no-op: subscriber receives event but does not modify types
            },
        );

        $resolver = new ContentSystemDataLoaderTypeResolver(
            ['navigation' => [['className' => Tree::class, 'genericParameters' => []]]],
            $dispatcher,
        );

        $map = $resolver->resolve();

        static::assertCount(1, $map->sourceToTypes['navigation']);
        static::assertSame(Tree::class, $map->sourceToTypes['navigation'][0]->className);
    }

    #[TestDox('resolves multiple sources with mixed subscriber presence')]
    public function testResolvesMultipleSourcesWithMixedSubscriberPresence(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ContentSystemDataLoaderTypesResolvedEvent::class . '.entity',
            static function (ContentSystemDataLoaderTypesResolvedEvent $event): void {
                $event->types = [new ContentSystemDataLoaderTypeDescriptor(ProductEntity::class)];
            },
        );

        $resolver = new ContentSystemDataLoaderTypeResolver([
            'entity' => [['className' => Entity::class, 'genericParameters' => []]],
            'navigation' => [['className' => Tree::class, 'genericParameters' => []]],
        ], $dispatcher);

        $map = $resolver->resolve();

        static::assertCount(2, $map->sourceToTypes);
        static::assertSame(ProductEntity::class, $map->sourceToTypes['entity'][0]->className);
        static::assertSame(Tree::class, $map->sourceToTypes['navigation'][0]->className);
    }

    #[TestDox('accepts empty type list when subscriber removes all types')]
    public function testAcceptsEmptyTypeListWhenSubscriberRemovesAllTypes(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ContentSystemDataLoaderTypesResolvedEvent::class . '.entity',
            static function (ContentSystemDataLoaderTypesResolvedEvent $event): void {
                $event->types = [];
            },
        );

        $resolver = new ContentSystemDataLoaderTypeResolver([
            'entity' => [['className' => Entity::class, 'genericParameters' => []]],
        ], $dispatcher);

        $map = $resolver->resolve();

        static::assertSame([], $map->sourceToTypes['entity']);
    }
}
