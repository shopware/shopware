<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Hydration\ContentElementHydrator;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\DataContextResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ContentElementHydrator::class)]
class ContentElementHydratorTest extends TestCase
{
    private SalesChannelContext $context;

    private RenderingCacheContext $cacheContext;

    protected function setUp(): void
    {
        $this->context = Generator::generateSalesChannelContext();
        $this->cacheContext = new RenderingCacheContext();
    }

    #[TestDox('loads data for elements with requirements and sets property')]
    public function testHydrateLoadsDataForElementsWithRequirements(): void
    {
        $element = ContentElementBuilder::create('product-card')
            ->withDataRequirement('product', 'entity', new StubLoaderConfig())
            ->build();

        $struct = new StubStruct();
        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('load')->willReturn(ContentDataLoaderResult::cached($struct, 'product-abc'));

        $hydrator = $this->createHydrator(['entity' => $loader]);

        $result = iterator_to_array($hydrator->hydrate([$element], $this->context, new Request(), $this->cacheContext), false);

        static::assertCount(1, $result);
        static::assertSame($struct, $element->getProperty('product'));
    }

    #[TestDox('skips elements without data requirements')]
    public function testHydrateSkipsElementsWithoutRequirements(): void
    {
        $element = ContentElementBuilder::create('text-block')->build();

        $hydrator = $this->createHydrator();

        $result = iterator_to_array($hydrator->hydrate([$element], $this->context, new Request(), $this->cacheContext), false);

        static::assertCount(1, $result);
    }

    #[TestDox('skips setting property when loader result has no data')]
    public function testHydrateSkipsPropertyWhenResultHasNoData(): void
    {
        $element = ContentElementBuilder::create('product-card')
            ->withDataRequirement('product', 'entity', new StubLoaderConfig())
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('load')->willReturn(ContentDataLoaderResult::notFound());

        $hydrator = $this->createHydrator(['entity' => $loader]);

        iterator_to_array($hydrator->hydrate([$element], $this->context, new Request(), $this->cacheContext), false);

        static::assertNull($element->getProperty('product'));
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

        iterator_to_array($hydrator->hydrate([$element], $this->context, new Request(), $this->cacheContext), false);

        static::assertSame('product-data', $child->getProperty('product'));
    }

    #[TestDox('disables cache when loader result is not cache aware')]
    public function testHydrateDisablesCacheWhenResultIsNotCacheAware(): void
    {
        $element = ContentElementBuilder::create('dynamic')
            ->withDataRequirement('data', 'entity', new StubLoaderConfig())
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('load')->willReturn(ContentDataLoaderResult::uncacheable(new StubStruct()));

        $hydrator = $this->createHydrator(['entity' => $loader]);

        iterator_to_array($hydrator->hydrate([$element], $this->context, new Request(), $this->cacheContext), false);

        static::assertTrue($this->cacheContext->isDisabled());
    }

    #[TestDox('adds cache tags from loader result to cache context')]
    public function testHydrateAddsCacheTagsFromResult(): void
    {
        $element = ContentElementBuilder::create('product-card')
            ->withDataRequirement('product', 'entity', new StubLoaderConfig())
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('load')->willReturn(ContentDataLoaderResult::cached(new StubStruct(), 'product-abc', 'product-def'));

        $hydrator = $this->createHydrator(['entity' => $loader]);

        iterator_to_array($hydrator->hydrate([$element], $this->context, new Request(), $this->cacheContext), false);

        static::assertSame(['product-abc', 'product-def'], $this->cacheContext->getTags());
    }

    #[TestDox('recurses into slot children for hydration')]
    public function testHydrateRecursesIntoSlotChildren(): void
    {
        $child = ContentElementBuilder::create('child')
            ->withDataRequirement('item', 'entity', new StubLoaderConfig())
            ->build();

        $parent = ContentElementBuilder::create('parent')
            ->withSlot('content', [$child])
            ->build();

        $childStruct = new StubStruct();
        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('load')->willReturn(ContentDataLoaderResult::cached($childStruct));

        $hydrator = $this->createHydrator(['entity' => $loader]);

        iterator_to_array($hydrator->hydrate([$parent], $this->context, new Request(), $this->cacheContext), false);

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
