<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentPipeline;
use Shopware\Core\Framework\ContentSystem\Event\ContentTreePreparationEvent;
use Shopware\Core\Framework\ContentSystem\Event\PostHydrationEvent;
use Shopware\Core\Framework\ContentSystem\Hydration\ContentElementHydrator;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\DataContextResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElementLowering;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\StoredTreePreparer;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\ElementTreePruner;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\Output\SubTreeExtractor;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderableLayout;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfig;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentPipeline::class)]
class ContentPipelineTest extends TestCase
{
    private ContentElementHydrator $hydrator;

    private EventDispatcherInterface&Stub $eventDispatcher;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->hydrator = new ContentElementHydrator(
            new DataLoaderProvider(new ServiceLocator([])),
            new LoaderInputResolver(),
            new DataContextResolver(new ContextPathResolver()),
        );
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
    }

    #[TestDox('dispatches preparation and post-hydration lifecycle events in order in FULL mode')]
    public function testLoadDispatchesLifecycleEventsInFullMode(): void
    {
        $layout = RenderableLayout::fromEntity($this->createLayoutEntity($this->ids->get('layout')));

        $dispatchedEvents = [];
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event::class;

                return $event;
            }
        );

        $pipeline = $this->createPipeline();
        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());

        $pipeline->load($layout, $specification, new RenderingCacheContext(), RenderingMode::FULL, Generator::generateSalesChannelContext());

        static::assertSame([ContentTreePreparationEvent::class, PostHydrationEvent::class], $dispatchedEvents);
    }

    #[TestDox('distributes provider context into consumer children in FULL mode')]
    public function testLoadHydratesElementsInFullMode(): void
    {
        $consumer = StoredElementBuilder::create('text', 'consumer-id')
            ->withConsumer('product', ContextType::Single)
            ->build();
        $provider = StoredElementBuilder::create('section', 'provider-id')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withProperty('product', 'product-payload')
            ->withSlot('default', [$consumer])
            ->build();
        $layout = RenderableLayout::create(
            LayoutReference::create($this->ids->get('layout'), 'Context Layout', '1.0'),
            [$provider]
        );

        // Fixture guard: only a consumer that is still unfilled makes the hydration branch observable.
        static::assertArrayHasKey('product', $consumer->contextDefinitions->getAllConsumers());
        static::assertNull($consumer->property('product'));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $pipeline = $this->createPipeline();
        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());

        $result = $pipeline->load($layout, $specification, new RenderingCacheContext(), RenderingMode::FULL, Generator::generateSalesChannelContext());

        static::assertSame('product-payload', $this->renderedElement($result->elements, 'consumer-id')->getProperty('product'));
    }

    #[TestDox('returns content page with original elements and layout metadata in SKELETON mode')]
    public function testLoadReturnsContentPageInSkeletonMode(): void
    {
        $layoutId = $this->ids->get('layout');
        $layout = RenderableLayout::fromEntity($this->createLayoutEntity($layoutId, 'My Layout'));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $pipeline = $this->createPipeline();
        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());

        $result = $pipeline->load($layout, $specification, new RenderingCacheContext(), RenderingMode::SKELETON, Generator::generateSalesChannelContext());

        $elements = iterator_to_array($result->elements, false);
        static::assertNotEmpty($elements);
        static::assertSame('section', $elements[0]->getComponent());
        static::assertSame($layoutId, $result->layoutId);
        static::assertSame('My Layout', $result->layoutName);
        static::assertSame('1.0', $result->layoutVersion);
    }

    #[DataProvider('renderingModeProvider')]
    #[TestDox('renders the forest a preparation subscriber put back instead of the loaded layout')]
    public function testLoadRendersTreeReplacedDuringPreparation(RenderingMode $mode): void
    {
        $layout = RenderableLayout::fromEntity($this->createLayoutEntity($this->ids->get('layout')));

        // Fixture guard: the layout must not already contain the injected element.
        static::assertSame([], array_filter(
            $this->collectStoredIds($layout->elements),
            static fn (string $id): bool => $id === 'injected-id'
        ));

        $injected = StoredElementBuilder::create('injected', 'injected-id')->build();
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use ($injected) {
                if ($event instanceof ContentTreePreparationEvent) {
                    $event->replaceTree([$injected]);
                }

                return $event;
            }
        );

        $pipeline = $this->createPipeline();
        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());

        $result = $pipeline->load($layout, $specification, new RenderingCacheContext(), $mode, Generator::generateSalesChannelContext());

        $elements = iterator_to_array($result->elements, false);
        static::assertCount(1, $elements);
        static::assertSame('injected-id', $elements[0]->getId());
    }

    /**
     * @return array<string, array{RenderingMode}>
     */
    public static function renderingModeProvider(): array
    {
        return [
            'SKELETON mode' => [RenderingMode::SKELETON],
            'FULL mode' => [RenderingMode::FULL],
        ];
    }

    #[TestDox('exposes the layout roots to preparation subscribers, before the virtual-root wrap')]
    public function testPreparationSubscribersSeeTheRootsBeforeVirtualRootWrapping(): void
    {
        $layout = $this->createSingleRootLayout(StoredElementBuilder::create('section', 'root-id')->build());
        $specification = new RenderingSpecification(
            [new DataRequirement('language', 'language', new LanguageLoaderConfig())],
            PlaceholderValues::from([]),
            new Request()
        );

        // Fixture guard: without page-level data requirements the pipeline never wraps at all.
        static::assertTrue((new VirtualRootWrapper())->requiresWrapping($specification, $layout->elements));

        $observed = null;
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use (&$observed) {
                if ($event instanceof ContentTreePreparationEvent) {
                    $observed = $this->collectStoredIds($event->tree());
                }

                return $event;
            }
        );

        $this->createPipeline()->load(
            $layout,
            $specification,
            new RenderingCacheContext(),
            RenderingMode::SKELETON,
            Generator::generateSalesChannelContext()
        );

        static::assertSame(['root-id'], $observed);
    }

    #[TestDox('exposes unresolved placeholders to preparation subscribers')]
    public function testPreparationSubscribersSeeUnresolvedPlaceholders(): void
    {
        $root = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', '{{productId}}')
            ->build();
        $layout = $this->createSingleRootLayout($root);
        $specification = new RenderingSpecification(
            [],
            PlaceholderValues::from(['productId' => 'resolved-product']),
            new Request()
        );

        $observed = null;
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            static function (object $event) use (&$observed) {
                if ($event instanceof ContentTreePreparationEvent) {
                    $observed = $event->tree()[0]->property('title')?->asString();
                }

                return $event;
            }
        );

        $result = $this->createPipeline()->load(
            $layout,
            $specification,
            new RenderingCacheContext(),
            RenderingMode::FULL,
            Generator::generateSalesChannelContext()
        );

        static::assertSame('{{productId}}', $observed);
        // Fixture guard: the placeholder really was resolvable, so the step ran after the dispatch.
        $elements = iterator_to_array($result->elements, false);
        static::assertSame('resolved-product', $elements[0]->getProperty('title'));
    }

    #[TestDox('exposes unexpanded redistribute consumers to preparation subscribers')]
    public function testPreparationSubscribersSeeUnexpandedRedistributeConsumers(): void
    {
        $root = StoredElementBuilder::create('section', 'root-id')
            ->withConsumer('product', ContextType::Single, redistribute: true)
            ->build();
        $layout = $this->createSingleRootLayout($root);

        $observed = null;
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            static function (object $event) use (&$observed) {
                if ($event instanceof ContentTreePreparationEvent) {
                    $observed = array_keys($event->tree()[0]->contextDefinitions->getAllProviders());
                }

                return $event;
            }
        );

        $result = $this->createPipeline()->load(
            $layout,
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            new RenderingCacheContext(),
            RenderingMode::SKELETON,
            Generator::generateSalesChannelContext()
        );

        static::assertSame([], $observed);
        // Fixture guard: the consumer really did redistribute, so the step ran after the dispatch.
        $elements = iterator_to_array($result->elements, false);
        static::assertArrayHasKey('product', $elements[0]->getProvidesContext());
    }

    #[TestDox('exposes the unpruned layout tree to preparation subscribers, before the partial prune')]
    public function testPreparationSubscribersSeeTheUnprunedTree(): void
    {
        $observed = null;
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use (&$observed) {
                if ($event instanceof ContentTreePreparationEvent) {
                    $observed = $this->collectStoredIds($event->tree());
                }

                return $event;
            }
        );

        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request(), 'target-id');

        $this->createPipeline()->load(
            $this->createPartialRenderLayout(),
            $specification,
            new RenderingCacheContext(),
            RenderingMode::SKELETON,
            Generator::generateSalesChannelContext()
        );

        // The sibling is what the prune drops: seeing it proves the prune had not run yet.
        static::assertSame(['root-id', 'target-id', 'sibling-id'], $observed);
    }

    #[TestDox('exposes the layout roots to PostHydration subscribers, after the virtual-root unwrap')]
    public function testPostHydrationSubscribersSeeTheRootsAfterVirtualRootUnwrapping(): void
    {
        $layout = $this->createSingleRootLayout(StoredElementBuilder::create('section', 'root-id')->build());
        $specification = new RenderingSpecification(
            [new DataRequirement('language', 'language', new LanguageLoaderConfig())],
            PlaceholderValues::from([]),
            new Request()
        );

        // Fixture guard: without page-level data requirements there is no virtual root to unwrap.
        static::assertTrue((new VirtualRootWrapper())->requiresWrapping($specification, $layout->elements));

        $observed = null;
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use (&$observed) {
                if ($event instanceof PostHydrationEvent) {
                    $observed = $this->collectIds($event->elements);
                }

                return $event;
            }
        );

        $this->createPipeline()->load(
            $layout,
            $specification,
            new RenderingCacheContext(),
            RenderingMode::SKELETON,
            Generator::generateSalesChannelContext()
        );

        static::assertSame(['root-id'], $observed);
    }

    #[TestDox('exposes the extracted partial-render subtree to PostHydration subscribers')]
    public function testPostHydrationSubscribersSeeTheExtractedSubtree(): void
    {
        $layout = $this->createPartialRenderLayout();

        // Fixture guard: the target consumes context, so the prune keeps its ancestor and the
        // extract has an ancestor left to remove.
        $target = $this->findChild($this->lower($layout->elements)[0], 'target-id');
        static::assertNotNull($target);
        static::assertTrue((new ContextDependencyAnalyzer())->requiresParentData($target));

        $observed = null;
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use (&$observed) {
                if ($event instanceof PostHydrationEvent) {
                    $observed = $this->collectIds($event->elements);
                }

                return $event;
            }
        );

        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request(), 'target-id');

        $this->createPipeline()->load(
            $layout,
            $specification,
            new RenderingCacheContext(),
            RenderingMode::SKELETON,
            Generator::generateSalesChannelContext()
        );

        static::assertSame(['target-id'], $observed);
    }

    #[TestDox('serves only the partial-render target when the prune already removed the virtual root')]
    public function testPartialRenderWhoseTargetNeedsNoPageContextDropsTheVirtualRoot(): void
    {
        $target = StoredElementBuilder::create('text', 'target-id')->build();
        $root = StoredElementBuilder::create('section', 'root-id')
            ->withSlot('default', [$target])
            ->build();
        $layout = $this->createSingleRootLayout($root);
        $specification = new RenderingSpecification(
            [new DataRequirement('language', 'language', new LanguageLoaderConfig())],
            PlaceholderValues::from([]),
            new Request(),
            'target-id'
        );

        $lowered = $this->lower($layout->elements);
        $loweredTarget = $this->findChild($lowered[0], 'target-id');
        static::assertNotNull($loweredTarget);

        // Fixture guard: the page-level data requirement really does make the pipeline wrap ...
        static::assertTrue((new VirtualRootWrapper())->requiresWrapping($specification, $layout->elements));
        // ... the target really does need no parent data ...
        static::assertFalse((new ContextDependencyAnalyzer())->requiresParentData($loweredTarget));
        // ... and so the prune really does cut the virtual root away above it, rather than there
        // never having been one. The wrap runs on the stored tree and the pruner reads the lowered
        // one, so the guard lowers in between exactly as the pipeline does.
        static::assertSame('target-id', (new ElementTreePruner())->pruneToPathAndDescendants(
            (new ContentElementLowering())->lower((new VirtualRootWrapper())->wrap($layout->elements, $specification)),
            'target-id',
            new ContextDependencyAnalyzer(),
        )->getId());

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $result = $this->createPipeline()->load(
            $layout,
            $specification,
            new RenderingCacheContext(),
            RenderingMode::SKELETON,
            Generator::generateSalesChannelContext()
        );

        static::assertSame(['target-id'], $this->collectIds($result->elements));
    }

    #[TestDox('delivers a page-level data requirement to a consuming root through the virtual root')]
    public function testPageLevelDataRequirementReachesAConsumingRootThroughTheVirtualRoot(): void
    {
        $root = StoredElementBuilder::create('section', 'root-id')
            ->withConsumer('language', ContextType::Single)
            ->build();
        $layout = $this->createSingleRootLayout($root);
        $specification = new RenderingSpecification(
            [new DataRequirement('language', 'language', new LanguageLoaderConfig())],
            PlaceholderValues::from([]),
            new Request()
        );

        // Fixture guard: the layout itself provides nothing and holds no value under the consumed key,
        // so the virtual root the pipeline wraps around it is the only possible source.
        static::assertSame([], $root->contextDefinitions->getAllProviders());
        static::assertNull($root->property('language'));

        $pageData = new StubStruct();
        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('configSpecification')->willReturn(new LoaderConfigSpecification([]));
        $loader->method('load')->willReturn(ContentDataLoaderResult::cached($pageData, 'language-1'));
        $this->hydrator = new ContentElementHydrator(
            new DataLoaderProvider(new ServiceLocator(['language' => static fn (): AbstractContentDataLoader => $loader])),
            new LoaderInputResolver(),
            new DataContextResolver(new ContextPathResolver()),
        );

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $result = $this->createPipeline()->load(
            $layout,
            $specification,
            new RenderingCacheContext(),
            RenderingMode::FULL,
            Generator::generateSalesChannelContext()
        );

        $elements = iterator_to_array($result->elements, false);
        static::assertSame($pageData, $elements[0]->getProperty('language'));
    }

    private function createPipeline(): ContentPipeline
    {
        return new ContentPipeline(
            $this->eventDispatcher,
            new StoredTreePreparer(),
            new ContentElementLowering(),
            new VirtualRootWrapper(),
            new PartialRenderer(new ElementTreePruner(), new ContextDependencyAnalyzer(), new SubTreeExtractor()),
            $this->hydrator,
        );
    }

    private function createSingleRootLayout(StoredElement $root): RenderableLayout
    {
        return RenderableLayout::create(
            LayoutReference::create($this->ids->get('layout'), 'Single Root Layout', '1.0'),
            [$root]
        );
    }

    private function createPartialRenderLayout(): RenderableLayout
    {
        $target = StoredElementBuilder::create('text', 'target-id')
            ->withConsumer('product', ContextType::Single)
            ->build();
        $sibling = StoredElementBuilder::create('text', 'sibling-id')->build();
        $root = StoredElementBuilder::create('section', 'root-id')
            ->withSlot('default', [$target, $sibling])
            ->build();

        return RenderableLayout::create(
            LayoutReference::create($this->ids->get('layout'), 'Partial Layout', '1.0'),
            [$root]
        );
    }

    /**
     * @param list<StoredElement> $tree
     *
     * @return list<ContentElement>
     */
    private function lower(array $tree): array
    {
        return (new ContentElementLowering())->lowerTree($tree);
    }

    private function findChild(ContentElement $parent, string $childId): ?ContentElement
    {
        foreach ($parent->allSlotElements() as $child) {
            if ($child->getId() === $childId) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @param iterable<ContentElement> $elements
     */
    private function renderedElement(iterable $elements, string $childId): ContentElement
    {
        $found = $this->findRenderedElement($elements, $childId);
        static::assertNotNull($found, \sprintf('No rendered element with id "%s" in the result.', $childId));

        return $found;
    }

    /**
     * @param iterable<ContentElement> $elements
     */
    private function findRenderedElement(iterable $elements, string $childId): ?ContentElement
    {
        foreach ($elements as $element) {
            if ($element->getId() === $childId) {
                return $element;
            }

            $found = $this->findRenderedElement($element->allSlotElements(), $childId);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param iterable<ContentElement> $elements
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function collectIds(iterable $elements, array $ids = []): array
    {
        foreach ($elements as $element) {
            $ids[] = $element->getId();
            $ids = $this->collectIds($element->allSlotElements(), $ids);
        }

        return $ids;
    }

    /**
     * @param list<StoredElement> $tree
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function collectStoredIds(array $tree, array $ids = []): array
    {
        foreach ($tree as $element) {
            $ids[] = $element->id;
            foreach ($element->slots as $children) {
                $ids = $this->collectStoredIds($children, $ids);
            }
        }

        return $ids;
    }

    private function createLayoutEntity(string $layoutId, string $name = 'Test Layout'): ContentLayoutEntity
    {
        $element = StoredElementBuilder::create('section')->build();
        $entity = new ContentLayoutEntity();
        $entity->setId($layoutId);
        $entity->setName($name);
        $entity->setVersion('1.0');
        $entity->setLayout([$element]);

        return $entity;
    }
}
