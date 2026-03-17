<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentSystemDataLoaderTypeDescriptor;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ContentSystemDataLoaderTypeResolver::class)]
class ContentSystemDataLoaderTypeResolverTest extends TestCase
{
    #[TestDox('passes compiled entries through with all descriptor fields preserved')]
    public function testCompiledPassthrough(): void
    {
        $resolver = new ContentSystemDataLoaderTypeResolver(
            new ServiceLocator([]),
            ['product_review' => [['className' => EntitySearchResult::class, 'genericParameters' => [ProductReviewCollection::class]]]],
        );

        $map = $resolver->resolve();

        static::assertCount(1, $map->sourceToTypes['product_review']);
        static::assertSame(EntitySearchResult::class, $map->sourceToTypes['product_review'][0]->className);
        static::assertSame([ProductReviewCollection::class], $map->sourceToTypes['product_review'][0]->genericParameters);
    }

    #[TestDox('replaces compiled entries with overridden types')]
    public function testOverriddenTypesReplaceCompiledEntries(): void
    {
        $loader = new ReplacingStubLoader();
        $locator = new ServiceLocator(['entity' => static fn () => $loader]);

        $resolver = new ContentSystemDataLoaderTypeResolver(
            $locator,
            ['entity' => [['className' => Entity::class, 'genericParameters' => []]]],
        );

        $map = $resolver->resolve();

        static::assertCount(1, $map->sourceToTypes['entity']);
        static::assertSame(ProductEntity::class, $map->sourceToTypes['entity'][0]->className);
    }

    #[TestDox('keeps compiled entries when override is a no-op')]
    public function testKeepsCompiledEntriesWhenOverrideIsNoOp(): void
    {
        $loader = new NoOpStubLoader();
        $locator = new ServiceLocator(['navigation' => static fn () => $loader]);

        $resolver = new ContentSystemDataLoaderTypeResolver(
            $locator,
            ['navigation' => [['className' => Tree::class, 'genericParameters' => []]]],
        );

        $map = $resolver->resolve();

        static::assertCount(1, $map->sourceToTypes['navigation']);
        static::assertSame(Tree::class, $map->sourceToTypes['navigation'][0]->className);
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<ProductEntity>
 */
class ReplacingStubLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'entity';
    }

    public function overrideProvidedTypes(array &$types): void
    {
        $types = [new ContentSystemDataLoaderTypeDescriptor(ProductEntity::class)];
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<Tree>
 */
class NoOpStubLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'navigation';
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}
