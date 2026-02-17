<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Content\ContentSystem\Hydration\ContentElementHydrator;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DataContextResolver;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\TestLoaderConfig;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\TestStruct;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ContentElementHydrator::class)]
class ContentElementHydratorTest extends TestCase
{
    #[TestDox('loads data for elements with requirements and sets property')]
    public function testHydrateLoadsDataForElementsWithRequirements(): void
    {
        $element = ContentElementBuilder::create('product-card')
            ->withDataRequirement('product', 'entity', new TestLoaderConfig())
            ->build();

        $struct = new TestStruct();
        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('load')->willReturn(ContentDataLoaderResult::cached($struct, 'product-abc'));

        $hydrator = $this->createHydrator(['entity' => $loader]);

        $context = Generator::generateSalesChannelContext();
        $cacheContext = new RenderingCacheContext();

        $result = iterator_to_array($hydrator->hydrate([$element], $context, new Request(), $cacheContext), false);

        static::assertCount(1, $result);
        static::assertSame($struct, $element->getProperty('product'));
    }

    #[TestDox('skips elements without data requirements')]
    public function testHydrateSkipsElementsWithoutRequirements(): void
    {
        $element = ContentElementBuilder::create('text-block')->build();

        $hydrator = $this->createHydrator();

        $context = Generator::generateSalesChannelContext();
        $cacheContext = new RenderingCacheContext();

        $result = iterator_to_array($hydrator->hydrate([$element], $context, new Request(), $cacheContext), false);

        static::assertCount(1, $result);
    }

    #[TestDox('resolves context after all data has been loaded')]
    public function testHydrateResolvesContextAfterAllDataLoaded(): void
    {
        $child = ContentElementBuilder::create('consumer')
            ->withConsumer('product', ContextType::Single, required: false)
            ->build();

        $element = ContentElementBuilder::create('section')
            ->withProperty('product', 'product-data')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('default', [$child])
            ->build();

        $hydrator = $this->createHydrator();

        $context = Generator::generateSalesChannelContext();
        $cacheContext = new RenderingCacheContext();

        iterator_to_array($hydrator->hydrate([$element], $context, new Request(), $cacheContext), false);

        static::assertSame('product-data', $child->getProperty('product'));
    }

    #[TestDox('disables cache when loader result is not cache aware')]
    public function testHydrateDisablesCacheWhenResultIsNotCacheAware(): void
    {
        $element = ContentElementBuilder::create('dynamic')
            ->withDataRequirement('data', 'entity', new TestLoaderConfig())
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('load')->willReturn(ContentDataLoaderResult::uncacheable(new TestStruct()));

        $hydrator = $this->createHydrator(['entity' => $loader]);

        $context = Generator::generateSalesChannelContext();
        $cacheContext = new RenderingCacheContext();

        iterator_to_array($hydrator->hydrate([$element], $context, new Request(), $cacheContext), false);

        static::assertTrue($cacheContext->isDisabled());
    }

    #[TestDox('adds cache tags from loader result to cache context')]
    public function testHydrateAddsCacheTagsFromResult(): void
    {
        $element = ContentElementBuilder::create('product-card')
            ->withDataRequirement('product', 'entity', new TestLoaderConfig())
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('load')->willReturn(ContentDataLoaderResult::cached(new TestStruct(), 'product-abc', 'product-def'));

        $hydrator = $this->createHydrator(['entity' => $loader]);

        $context = Generator::generateSalesChannelContext();
        $cacheContext = new RenderingCacheContext();

        iterator_to_array($hydrator->hydrate([$element], $context, new Request(), $cacheContext), false);

        static::assertSame(['product-abc', 'product-def'], $cacheContext->getTags());
    }

    #[TestDox('recurses into slot children for hydration')]
    public function testHydrateRecursesIntoSlotChildren(): void
    {
        $child = ContentElementBuilder::create('child')
            ->withDataRequirement('item', 'entity', new TestLoaderConfig())
            ->build();

        $parent = ContentElementBuilder::create('parent')
            ->withSlot('content', [$child])
            ->build();

        $childStruct = new TestStruct();
        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('load')->willReturn(ContentDataLoaderResult::cached($childStruct));

        $hydrator = $this->createHydrator(['entity' => $loader]);

        $context = Generator::generateSalesChannelContext();
        $cacheContext = new RenderingCacheContext();

        iterator_to_array($hydrator->hydrate([$parent], $context, new Request(), $cacheContext), false);

        static::assertSame($childStruct, $child->getProperty('item'));
    }

    /**
     * @param array<string, AbstractContentDataLoader> $loaders
     */
    private function createHydrator(array $loaders = []): ContentElementHydrator
    {
        $factories = [];
        foreach ($loaders as $key => $loader) {
            $factories[$key] = static fn () => $loader;
        }

        return new ContentElementHydrator(
            new DataLoaderProvider(new ServiceLocator($factories)),
            new DataContextResolver(new ContextPathResolver()),
        );
    }
}
