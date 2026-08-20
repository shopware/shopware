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
use Shopware\Core\Framework\ContentSystem\Event\RenderedTreeFinalizationEvent;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
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
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\ElementTreePruner;
use Shopware\Core\Framework\ContentSystem\Output\Index\LoaderValueIdentityFactory;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndexFactory;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueFingerprinter;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\Output\SubTreeExtractor;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderableLayout;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDeliveryResolver;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDistributor;
use Shopware\Core\Framework\ContentSystem\Rendering\ElementDataResolver;
use Shopware\Core\Framework\ContentSystem\Rendering\ElementLowering;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElementFactory;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedTreeFactory;
use Shopware\Core\Framework\ContentSystem\Rendering\WiringPlanner;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfig;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfigSerializer;
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
    private ElementLowering $lowering;

    private EventDispatcherInterface&Stub $eventDispatcher;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->lowering = $this->createLowering([]);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
    }

    #[TestDox('dispatches preparation and rendered-tree finalization lifecycle events in order in FULL mode')]
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

        $pipeline->load($layout, $specification, new RenderingCacheContext(), RenderingMode::FULL, false, Generator::generateSalesChannelContext());

        static::assertSame([ContentTreePreparationEvent::class, RenderedTreeFinalizationEvent::class], $dispatchedEvents);
    }

    #[DataProvider('renderingModeProvider')]
    #[TestDox('dispatches the rendered-tree finalization event in either rendering mode')]
    public function testLoadDispatchesTheFinalizationEventInBothModes(RenderingMode $mode): void
    {
        $layout = RenderableLayout::fromEntity($this->createLayoutEntity($this->ids->get('layout')));

        $dispatchedEvents = [];
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event::class;

                return $event;
            }
        );

        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());

        $this->createPipeline()->load($layout, $specification, new RenderingCacheContext(), $mode, false, Generator::generateSalesChannelContext());

        static::assertContains(RenderedTreeFinalizationEvent::class, $dispatchedEvents);
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

        $result = $pipeline->load($layout, $specification, new RenderingCacheContext(), RenderingMode::FULL, false, Generator::generateSalesChannelContext());

        static::assertSame('product-payload', $this->renderedElement($result->page->elements, 'consumer-id')->getProperty('product'));
    }

    #[TestDox('returns content page with original elements and layout metadata in SKELETON mode')]
    public function testLoadReturnsContentPageInSkeletonMode(): void
    {
        $layoutId = $this->ids->get('layout');
        $layout = RenderableLayout::fromEntity($this->createLayoutEntity($layoutId, 'My Layout'));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $pipeline = $this->createPipeline();
        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());

        $result = $pipeline->load($layout, $specification, new RenderingCacheContext(), RenderingMode::SKELETON, false, Generator::generateSalesChannelContext());

        $elements = iterator_to_array($result->page->elements, false);
        static::assertNotEmpty($elements);
        static::assertSame('section', $elements[0]->getComponent());
        static::assertSame($layoutId, $result->page->layoutId);
        static::assertSame('My Layout', $result->page->layoutName);
        static::assertSame('1.0', $result->page->layoutVersion);
    }

    #[TestDox('returns the finished rendered forest beside the bridged page, both describing the same elements')]
    public function testLoadReturnsTheRenderedForestBesideTheBridgedPage(): void
    {
        $layoutId = $this->ids->get('layout');
        $layout = RenderableLayout::fromEntity($this->createLayoutEntity($layoutId, 'My Layout'));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());

        $result = $this->createPipeline()->load($layout, $specification, new RenderingCacheContext(), RenderingMode::FULL, false, Generator::generateSalesChannelContext());

        static::assertSame($layoutId, $result->reference->id);
        static::assertSame('My Layout', $result->reference->name);
        static::assertSame(
            array_map(static fn (ContentElement $element): string => $element->getId(), iterator_to_array($result->page->elements, false)),
            array_map(static fn (RenderedElement $element): string => $element->id, $result->tree),
        );
    }

    #[DataProvider('renderingModeProvider')]
    #[TestDox('builds a value index when the format asks for one and none when it does not')]
    public function testLoadCollectsAValueIndexOnlyWhenAsked(RenderingMode $mode): void
    {
        $layout = RenderableLayout::fromEntity($this->createLayoutEntity($this->ids->get('layout'), 'My Layout'));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());
        $pipeline = $this->createPipeline();

        $requested = $pipeline->load($layout, $specification, new RenderingCacheContext(), $mode, true, Generator::generateSalesChannelContext());
        $notRequested = $pipeline->load($layout, $specification, new RenderingCacheContext(), $mode, false, Generator::generateSalesChannelContext());

        // The flag is a format question, so it decides alone. SKELETON with collection asked for is a pair no
        // shipped route produces, and it yields an EMPTY index rather than a throw: a mode that resolves no
        // data has nothing to index, and a mode branch here is exactly what the pipeline does not have.
        static::assertNotNull($requested->index);
        static::assertNull($notRequested->index);
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

        $result = $pipeline->load($layout, $specification, new RenderingCacheContext(), $mode, false, Generator::generateSalesChannelContext());

        $elements = iterator_to_array($result->page->elements, false);
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
            false,
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
            false,
            Generator::generateSalesChannelContext()
        );

        static::assertSame('{{productId}}', $observed);
        // Fixture guard: the placeholder really was resolvable, so the step ran after the dispatch.
        $elements = iterator_to_array($result->page->elements, false);
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
            false,
            Generator::generateSalesChannelContext()
        );

        static::assertSame([], $observed);
        // Fixture guard: the consumer really did redistribute, so the step ran after the dispatch.
        $elements = iterator_to_array($result->page->elements, false);
        static::assertArrayHasKey('product', $elements[0]->getProvidesContext());
    }

    #[TestDox('carries the expanded redistribute provider into the tree the pipeline renders')]
    public function testRedistributeExpansionResultReachesTheRenderedTree(): void
    {
        $consumer = StoredElementBuilder::create('text', 'consumer-id')
            ->withConsumer('product', ContextType::Single)
            ->build();
        $redistributor = StoredElementBuilder::create('section', 'redistributor-id')
            ->withConsumer('product', ContextType::Single, redistribute: true)
            ->withProperty('product', 'product-payload')
            ->withSlot('default', [$consumer])
            ->build();
        $layout = $this->createSingleRootLayout($redistributor);

        // Fixture guard: the authored tree declares no provider at all, so the only way the payload
        // can reach the child is through the provider the expansion derives.
        static::assertSame([], $redistributor->contextDefinitions->getAllProviders());
        static::assertNull($consumer->property('product'));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $result = $this->createPipeline()->load(
            $layout,
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            new RenderingCacheContext(),
            RenderingMode::FULL,
            false,
            Generator::generateSalesChannelContext()
        );

        static::assertSame('product-payload', $this->renderedElement($result->page->elements, 'consumer-id')->getProperty('product'));
    }

    #[TestDox('carries context down a second redistribution hop to a grandchild')]
    public function testRedistributionChainsToAGrandchild(): void
    {
        $grandchild = StoredElementBuilder::create('text', 'grandchild-id')
            ->withConsumer('product', ContextType::Single)
            ->build();
        $middle = StoredElementBuilder::create('section', 'middle-id')
            ->withConsumer('product', ContextType::Single, redistribute: true)
            ->withSlot('default', [$grandchild])
            ->build();
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withProvider('product', BroadcastDistributionConfig::simple())
                ->withProperty('product', 'product-payload')
                ->withSlot('default', [$middle])
                ->build()
        );

        // Fixture guard: the middle element stores nothing of its own, so the only value it can
        // redistribute is the one the root delivers to it — this is a genuine two-hop chain.
        static::assertNull($middle->property('product'));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $result = $this->createPipeline()->load(
            $layout,
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            new RenderingCacheContext(),
            RenderingMode::FULL,
            false,
            Generator::generateSalesChannelContext()
        );

        static::assertSame('product-payload', $this->renderedElement($result->page->elements, 'grandchild-id')->getProperty('product'));
    }

    #[TestDox('carries context down a second redistribution hop that renames the key for children')]
    public function testRedistributionChainsToAGrandchildUnderAConsumerAlias(): void
    {
        $grandchild = StoredElementBuilder::create('text', 'grandchild-id')
            ->withConsumer('product', ContextType::Single)
            ->build();
        $middle = StoredElementBuilder::create('section', 'middle-id')
            ->withConsumer('featuredProduct', ContextType::Single, redistribute: true, consumerAlias: 'product')
            ->withSlot('default', [$grandchild])
            ->build();
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withProvider('featuredProduct', BroadcastDistributionConfig::simple())
                ->withProperty('featuredProduct', 'product-payload')
                ->withSlot('default', [$middle])
                ->build()
        );

        // Fixture guard: the middle element stores nothing of its own, so the derived provider has to
        // read back the value the root delivered under the accepted key, not under the alias.
        static::assertNull($middle->property('featuredProduct'));
        static::assertNull($middle->property('product'));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $result = $this->createPipeline()->load(
            $layout,
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            new RenderingCacheContext(),
            RenderingMode::FULL,
            false,
            Generator::generateSalesChannelContext()
        );

        static::assertSame('product-payload', $this->renderedElement($result->page->elements, 'grandchild-id')->getProperty('product'));
    }

    #[TestDox('delivers the same value whether a container redistributes or wires accept and provide by hand')]
    public function testRedistributeShorthandMatchesTheManualAcceptAndProvidePair(): void
    {
        $shorthandChild = StoredElementBuilder::create('text', 'shorthand-child-id')
            ->withConsumer('product', ContextType::Single)
            ->build();
        $shorthand = StoredElementBuilder::create('section', 'shorthand-id')
            ->withConsumer('featuredProduct', ContextType::Single, redistribute: true, consumerAlias: 'product')
            ->withSlot('default', [$shorthandChild])
            ->build();

        $manualChild = StoredElementBuilder::create('text', 'manual-child-id')
            ->withConsumer('product', ContextType::Single)
            ->build();
        $manual = StoredElementBuilder::create('section', 'manual-id')
            ->withConsumer('featuredProduct', ContextType::Single)
            ->withProvider('featuredProduct', BroadcastDistributionConfig::aliased('product'))
            ->withSlot('default', [$manualChild])
            ->build();

        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withProvider('featuredProduct', BroadcastDistributionConfig::simple())
                ->withProperty('featuredProduct', 'product-payload')
                ->withSlot('default', [$shorthand, $manual])
                ->build()
        );

        // Fixture guard: the two containers differ only in how they are wired — neither holds a value.
        static::assertNull($shorthand->property('featuredProduct'));
        static::assertNull($manual->property('featuredProduct'));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $result = $this->createPipeline()->load(
            $layout,
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            new RenderingCacheContext(),
            RenderingMode::FULL,
            false,
            Generator::generateSalesChannelContext()
        );

        static::assertSame('product-payload', $this->renderedElement($result->page->elements, 'shorthand-child-id')->getProperty('product'));
        static::assertSame('product-payload', $this->renderedElement($result->page->elements, 'manual-child-id')->getProperty('product'));
    }

    /**
     * The derived provider is serialized verbatim into a full-format response, so its distribution config
     * is wire-visible on the rendered element the pipeline serves. A derivation that always carried an
     * alias would rename nothing yet still change the body of every plain redistribution.
     */
    #[TestDox('serializes a derived redistribute provider carrying an alias only where the key is renamed')]
    #[DataProvider('derivedProviderWireShapeProvider')]
    public function testDerivedRedistributeProviderSerializesItsConsumerAliasOnTheRenderedTree(?string $consumerAlias, ?string $expectedSerializedAlias): void
    {
        $middle = StoredElementBuilder::create('section', 'middle-id')
            ->withConsumer('featuredProduct', ContextType::Single, redistribute: true, consumerAlias: $consumerAlias)
            ->withSlot('default', [
                StoredElementBuilder::create('text', 'grandchild-id')
                    ->withConsumer($consumerAlias ?? 'featuredProduct', ContextType::Single)
                    ->build(),
            ])
            ->build();
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withProvider('featuredProduct', BroadcastDistributionConfig::simple())
                ->withProperty('featuredProduct', 'product-payload')
                ->withSlot('default', [$middle])
                ->build()
        );

        // Fixture guard: the middle element authors no provider, so the only provider that can appear
        // in the served response is the one the redistribution derivation produced.
        static::assertSame([], $middle->contextDefinitions->getAllProviders());

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $result = $this->createPipeline()->load(
            $layout,
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            new RenderingCacheContext(),
            RenderingMode::FULL,
            false,
            Generator::generateSalesChannelContext()
        );

        $serialized = $this->renderedElement($result->page->elements, 'middle-id')->jsonSerialize();

        static::assertSame(
            ['type' => 'single', 'distribution' => 'broadcast', 'consumerAlias' => $expectedSerializedAlias],
            $serialized['providesContext']['featuredProduct']
        );
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function derivedProviderWireShapeProvider(): iterable
    {
        yield 'no alias keeps the plain config' => [null, null];
        yield 'alias is carried through' => ['product', 'product'];
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
            false,
            Generator::generateSalesChannelContext()
        );

        // The sibling is what the prune drops: seeing it proves the prune had not run yet.
        static::assertSame(['root-id', 'target-id', 'sibling-id'], $observed);
    }

    #[TestDox('exposes the layout roots to finalization subscribers, after the virtual-root unwrap')]
    public function testFinalizationSubscribersSeeTheRootsAfterVirtualRootUnwrapping(): void
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
                if ($event instanceof RenderedTreeFinalizationEvent) {
                    $observed = $this->collectRenderedIds($event->tree());
                }

                return $event;
            }
        );

        $this->createPipeline()->load(
            $layout,
            $specification,
            new RenderingCacheContext(),
            RenderingMode::SKELETON,
            false,
            Generator::generateSalesChannelContext()
        );

        static::assertSame(['root-id'], $observed);
    }

    #[TestDox('exposes the extracted partial-render subtree to finalization subscribers')]
    public function testFinalizationSubscribersSeeTheExtractedSubtree(): void
    {
        $layout = $this->createPartialRenderLayout();

        // Fixture guard: the target consumes context, so the prune keeps its ancestor and the
        // extract has an ancestor left to remove.
        $target = $this->findStoredChild($layout->elements[0], 'target-id');
        static::assertNotNull($target);
        static::assertTrue((new ContextDependencyAnalyzer())->requiresParentData($target));

        $observed = null;
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            function (object $event) use (&$observed) {
                if ($event instanceof RenderedTreeFinalizationEvent) {
                    $observed = $this->collectRenderedIds($event->tree());
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
            false,
            Generator::generateSalesChannelContext()
        );

        static::assertSame(['target-id'], $observed);
    }

    #[TestDox('hands finalization subscribers the rendered element model, not the bridged one')]
    public function testFinalizationSubscribersSeeTheRenderedModel(): void
    {
        $layout = $this->createSingleRootLayout(StoredElementBuilder::create('text', 'root-id')->build());

        $observed = null;
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            static function (object $event) use (&$observed) {
                if ($event instanceof RenderedTreeFinalizationEvent) {
                    $observed = $event->tree();
                }

                return $event;
            }
        );

        $this->createPipeline()->load(
            $layout,
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            new RenderingCacheContext(),
            RenderingMode::FULL,
            false,
            Generator::generateSalesChannelContext()
        );

        static::assertIsArray($observed);
        static::assertCount(1, $observed);
        static::assertContainsOnlyInstancesOf(RenderedElement::class, $observed);
    }

    #[TestDox('serves the forest a finalization subscriber put back instead of the rendered one')]
    public function testLoadServesTheTreeReplacedDuringFinalization(): void
    {
        $root = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'authored-title')
            ->build();
        $layout = $this->createSingleRootLayout($root);

        // Fixture guard: the authored value differs from the replacement, so the served title can only
        // read 'replaced-title' if the bridge lowered the forest the subscriber handed back.
        static::assertSame('authored-title', $root->property('title')?->asString());

        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            static function (object $event) {
                if ($event instanceof RenderedTreeFinalizationEvent) {
                    $event->replaceTree([$event->tree()[0]->withProperty('title', 'replaced-title')]);
                }

                return $event;
            }
        );

        $result = $this->createPipeline()->load(
            $layout,
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            new RenderingCacheContext(),
            RenderingMode::FULL,
            false,
            Generator::generateSalesChannelContext()
        );

        $elements = iterator_to_array($result->page->elements, false);
        static::assertCount(1, $elements);
        static::assertSame('replaced-title', $elements[0]->getProperty('title'));
    }

    #[TestDox('delivers a derived redistribute provider to a child inside the surviving partial-render subtree')]
    public function testRedistributeDerivationSurvivesThePartialPrune(): void
    {
        $consumer = StoredElementBuilder::create('text', 'consumer-id')
            ->withConsumer('product', ContextType::Single)
            ->build();
        $redistributor = StoredElementBuilder::create('section', 'redistributor-id')
            ->withConsumer('product', ContextType::Single, redistribute: true)
            ->withProperty('product', 'product-payload')
            ->withSlot('default', [$consumer])
            ->build();
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withSlot('default', [
                    $redistributor,
                    StoredElementBuilder::create('text', 'sibling-id')->build(),
                ])
                ->build()
        );

        // Fixture guard: the authored tree declares no provider, so only the derived one can carry the
        // payload down, and the sibling proves the prune really ran.
        static::assertSame([], $redistributor->contextDefinitions->getAllProviders());

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $result = $this->createPipeline()->load(
            $layout,
            new RenderingSpecification([], PlaceholderValues::from([]), new Request(), 'redistributor-id'),
            new RenderingCacheContext(),
            RenderingMode::FULL,
            false,
            Generator::generateSalesChannelContext()
        );

        // Why this test exists: the derivation moved from the pre-prune forest onto the surviving tree.
        // That is safe today only because the derivation is node-local and the prune never rewrites a
        // survivor's wiring. Nothing else pins that pairing — every other redistribute test either runs
        // without a partial render or asserts a throw — so this is the test that fails when a future
        // change makes the derivation depend on a node the prune has removed.
        static::assertSame(['redistributor-id', 'consumer-id'], $this->collectIds($result->page->elements));
        static::assertSame('product-payload', $this->renderedElement($result->page->elements, 'consumer-id')->getProperty('product'));
    }

    #[TestDox('never loads the data a subtree the partial prune discards would have required')]
    public function testPartialRenderDoesNotHydrateThePrunedAwaySibling(): void
    {
        $target = StoredElementBuilder::create('text', 'target-id')->build();
        $discarded = StoredElementBuilder::create('text', 'discarded-id')
            ->withDataRequirement('language', 'language', new LanguageLoaderConfig())
            ->build();
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', 'root-id')
                ->withSlot('default', [$target, $discarded])
                ->build()
        );

        // Fixture guard: the target needs no parent data, so the prune stops at it and the sibling
        // carrying the requirement is what the prune drops.
        static::assertFalse((new ContextDependencyAnalyzer())->requiresParentData($target));
        static::assertArrayHasKey('language', $discarded->dataRequirements);

        $loads = 0;
        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('configSpecification')->willReturn(new LoaderConfigSpecification([]));
        $loader->method('load')->willReturnCallback(
            function () use (&$loads): ContentDataLoaderResult {
                ++$loads;

                return ContentDataLoaderResult::cached(new StubStruct(), 'language-1');
            }
        );
        $this->lowering = $this->createLowering(['language' => $loader]);

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $result = $this->createPipeline()->load(
            $layout,
            new RenderingSpecification([], PlaceholderValues::from([]), new Request(), 'target-id'),
            new RenderingCacheContext(),
            RenderingMode::FULL,
            false,
            Generator::generateSalesChannelContext()
        );

        static::assertSame(['target-id'], $this->collectIds($result->page->elements));
        static::assertSame(0, $loads);
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
        $this->lowering = $this->createLowering(['language' => $loader]);

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $result = $this->createPipeline()->load(
            $layout,
            $specification,
            new RenderingCacheContext(),
            RenderingMode::FULL,
            false,
            Generator::generateSalesChannelContext()
        );

        $elements = iterator_to_array($result->page->elements, false);
        static::assertSame($pageData, $elements[0]->getProperty('language'));
    }

    private function createPipeline(): ContentPipeline
    {
        return new ContentPipeline(
            $this->eventDispatcher,
            new StoredTreePreparer(
                new VirtualRootWrapper(),
                new PartialRenderer(new ElementTreePruner(), new ContextDependencyAnalyzer(), new SubTreeExtractor()),
            ),
            new WiringPlanner(),
            $this->lowering,
            new ContentElementLowering(),
            new VirtualRootWrapper(),
            new PartialRenderer(new ElementTreePruner(), new ContextDependencyAnalyzer(), new SubTreeExtractor()),
            new ResolvedValueIndexFactory($this->typeRegistry(), new ValueFingerprinter()),
        );
    }

    /**
     * The render layers are real below the loader seam and the element type registry: a real
     * `ElementDataResolver` over a real `LoaderInputResolver`, a real `ContextDeliveryResolver` over a real
     * `ContextDistributor`, and a real `RenderedTreeFactory` over a real `RenderedElementFactory`. Only the
     * data loaders and the type registry are doubles, so what the pipeline serves is what those layers
     * actually produced.
     *
     * @param array<string, AbstractContentDataLoader<Struct>> $loaders keyed by loader source
     */
    private function createLowering(array $loaders): ElementLowering
    {
        $factories = [];
        $serializers = [];
        foreach ($loaders as $source => $loader) {
            $factories[$source] = static fn (): AbstractContentDataLoader => $loader;
            // Every stubbed source gets a config serializer, because the render path now hashes each
            // requirement's config to build the value's dedup identity — a source without one is a
            // misconfiguration the real provider refuses, not a case these fixtures should model.
            $serializers[$source] = static fn (): StubLoaderConfigSerializer => new StubLoaderConfigSerializer();
        }

        return new ElementLowering(
            new ElementDataResolver(
                new DataLoaderProvider(new ServiceLocator($factories)),
                new LoaderInputResolver(),
                new LoaderValueIdentityFactory(
                    new DataLoaderConfigSerializerProvider(new ServiceLocator($serializers)),
                    new ConfigCanonicalizer(),
                    new ValueFingerprinter(),
                ),
            ),
            new ContextDeliveryResolver(new ContextDistributor(new ContextPathResolver())),
            new RenderedTreeFactory(new RenderedElementFactory($this->typeRegistry())),
        );
    }

    /**
     * A rendered property map is derived from the element's type, not copied from storage, so a stored key
     * only renders where the type declares it. `text` therefore declares the one primitive whose stored
     * value a test below reads back off the served element. Every other component these fixtures use stores
     * nothing it serves and stays unregistered, which is also the shape the virtual root is in.
     */
    private function typeRegistry(): AbstractContentSystemElementTypeRegistry
    {
        $specs = [
            'text' => ContentSystemElementTypeSpecificationBuilder::create('text')
                ->primitive('title', 'string')
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(
            static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]
        );

        return $registry;
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

    private function findStoredChild(StoredElement $parent, string $childId): ?StoredElement
    {
        foreach ($parent->slots as $children) {
            foreach ($children as $child) {
                if ($child->id === $childId) {
                    return $child;
                }
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

    /**
     * @param list<RenderedElement> $tree
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function collectRenderedIds(array $tree, array $ids = []): array
    {
        foreach ($tree as $element) {
            $ids[] = $element->id;
            foreach ($element->slots as $children) {
                $ids = $this->collectRenderedIds($children, $ids);
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
