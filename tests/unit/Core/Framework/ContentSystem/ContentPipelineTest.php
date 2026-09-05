<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentPipeline;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
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
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ProviderDeliveryKeyResolver;
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

    private DataLoaderConfigSerializerProvider $configSerializers;

    private EventDispatcherInterface&Stub $eventDispatcher;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->lowering = $this->createLowering([]);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
    }

    #[DataProvider('supportsRenderingModeProvider')]
    #[TestDox('returns the rendered forest beside the layout reference triple in either rendering mode')]
    public function testLoadReturnsTheRenderedForestAndTheLayoutReference(RenderingMode $mode): void
    {
        $layoutId = $this->ids->get('layout');
        $layout = RenderableLayout::fromEntity($this->createLayoutEntity($layoutId, 'My Layout'));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $pipeline = $this->createPipeline();
        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());

        $result = $pipeline->load($layout, $specification, new RenderingCacheContext(), $mode, false, Generator::generateSalesChannelContext());

        static::assertNotEmpty($result->tree);
        static::assertSame('section', $result->tree[0]->component);
        static::assertSame($layoutId, $result->reference->id);
        static::assertSame('My Layout', $result->reference->name);
        static::assertSame('1.0', $result->reference->version);
    }

    /**
     * @param array<string, array<string, mixed>> $expectedResolvedAssignments
     * @param list<string> $expectedIndexedValues
     */
    #[DataProvider('indexesRenderedPropertiesProvider')]
    #[TestDox('builds a value index when the format asks for one and none when it does not')]
    public function testLoadCollectsAValueIndexOnlyWhenAsked(
        RenderingMode $mode,
        array $expectedResolvedAssignments,
        array $expectedIndexedValues,
    ): void {
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('text', 'root-id')->withProperty('title', 'authored-title')->build()
        );

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request());
        $pipeline = $this->createPipeline();

        $requested = $pipeline->load($layout, $specification, new RenderingCacheContext(), $mode, true, Generator::generateSalesChannelContext());
        $notRequested = $pipeline->load($layout, $specification, new RenderingCacheContext(), $mode, false, Generator::generateSalesChannelContext());

        // The flag is a format question, so it decides alone: the index exists in either mode when it is asked
        // for, and never when it is not. What the index HOLDS is the mode's business, and that is what the two
        // rows discriminate, because SKELETON mints no property at all and so has nothing to index.
        $index = $requested->index;
        static::assertNotNull($index);

        // Refs are response-local, so the assignment map is read through the ref rather than asserted on it.
        // Resolving each one also ties the two halves together: an assignment map that lost its property keys
        // while the data map kept the value would satisfy either half read on its own.
        $resolved = [];
        foreach ($index->assignments() as $elementId => $refs) {
            foreach ($refs as $key => $ref) {
                $resolved[$elementId][$key] = $index->value($ref);
            }
        }

        static::assertSame($expectedResolvedAssignments, $resolved);
        // The data map holds nothing beyond what the assignments point at, so no orphan ref is served.
        static::assertSame($expectedIndexedValues, array_values($index->data()));
        static::assertNull($notRequested->index);
    }

    #[DataProvider('supportsRenderingModeProvider')]
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

        $elements = $result->tree;
        static::assertCount(1, $elements);
        static::assertSame('injected-id', $elements[0]->id);
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
        $elements = $result->tree;
        static::assertSame('resolved-product', $elements[0]->properties['title']);
    }

    #[TestDox('exposes unexpanded redistribute consumers to preparation subscribers')]
    public function testPreparationSubscribersSeeUnexpandedRedistributeConsumers(): void
    {
        // The child is what makes the derivation observable at all: the rendered model carries no context
        // wiring, so the only evidence a provider was derived is the value it delivered to a child.
        $child = StoredElementBuilder::create('text', 'child-id')
            ->withConsumer('product', ContextType::Single)
            ->build();
        $root = StoredElementBuilder::create('section', 'root-id')
            ->withConsumer('product', ContextType::Single, redistribute: true)
            ->withProperty('product', 'product-payload')
            ->withSlot('default', [$child])
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
            RenderingMode::FULL,
            false,
            Generator::generateSalesChannelContext()
        );

        static::assertSame([], $observed);
        // Fixture guard: the consumer really did redistribute, so the step ran after the dispatch.
        static::assertSame('product-payload', $this->renderedElement($result->tree, 'child-id')->properties['product']);
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

        static::assertSame('product-payload', $this->renderedElement($result->tree, 'consumer-id')->properties['product']);
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

        static::assertSame('product-payload', $this->renderedElement($result->tree, 'grandchild-id')->properties['product']);
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

        static::assertSame('product-payload', $this->renderedElement($result->tree, 'grandchild-id')->properties['product']);
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

        static::assertSame('product-payload', $this->renderedElement($result->tree, 'shorthand-child-id')->properties['product']);
        static::assertSame('product-payload', $this->renderedElement($result->tree, 'manual-child-id')->properties['product']);
    }

    /**
     * The derived provider carries an alias only where the redistributing consumer renamed the key, and the
     * rendered model shows that as the property key the grandchild receives the value under. A derivation
     * that always carried the alias would deliver nothing in the un-aliased case; one that never carried it
     * would deliver nothing in the aliased case.
     */
    #[DataProvider('derivesProviderKeyOnlyWhereAliasedProvider')]
    #[TestDox('derives a redistribute provider that renames the key for children only where an alias was declared')]
    public function testDerivedRedistributeProviderRenamesTheKeyOnlyWhereAliased(?string $consumerAlias, string $expectedDeliveredKey): void
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

        $grandchild = $this->renderedElement($result->tree, 'grandchild-id');

        static::assertArrayHasKey($expectedDeliveredKey, $grandchild->properties);
        static::assertSame('product-payload', $grandchild->properties[$expectedDeliveredKey]);
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

    #[TestDox('hands finalization subscribers the rendered element model')]
    public function testFinalizationSubscribersSeeTheRenderedModel(): void
    {
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('text', 'root-id')->withProperty('title', 'authored-title')->build()
        );

        $observed = null;
        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            static function (object $event) use (&$observed) {
                if ($event instanceof RenderedTreeFinalizationEvent) {
                    $projected = [];
                    foreach ($event->tree() as $element) {
                        $projected[] = [$element::class, $element->id, $element->component, $element->properties];
                    }

                    $observed = $projected;
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

        // `tree()` declares a bare `array` return, so only the annotation says what is inside it: the element
        // class is carried in the projection because nothing at runtime guarantees it. `properties` beside it
        // is the rendered model's raw unwrapped value, which a StoredElement holds only wrapped and behind
        // `property()`, and the id separates the layout root from a virtual-root wrapper or another branch.
        static::assertSame(
            [[RenderedElement::class, 'root-id', 'text', ['title' => 'authored-title']]],
            $observed
        );
    }

    #[TestDox('serves the forest a finalization subscriber put back instead of the rendered one')]
    public function testLoadServesTheTreeReplacedDuringFinalization(): void
    {
        $root = StoredElementBuilder::create('text', 'root-id')
            ->withProperty('title', 'authored-title')
            ->build();
        $layout = $this->createSingleRootLayout($root);

        // Fixture guard: the authored value differs from the replacement, so the served title can only
        // read 'replaced-title' if the result carries the forest the subscriber handed back.
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

        $elements = $result->tree;
        static::assertCount(1, $elements);
        static::assertSame('replaced-title', $elements[0]->properties['title']);
    }

    #[TestDox('serves an element a finalization subscriber added to the forest')]
    public function testLoadServesAnElementAddedDuringFinalization(): void
    {
        $layout = $this->createSingleRootLayout(StoredElementBuilder::create('text', 'root-id')->build());

        // Fixture guard: the added element exists nowhere in the stored tree, so it can only reach the result
        // by being minted inside the subscriber.
        static::assertSame(['root-id'], $this->collectStoredIds($layout->elements));

        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            static function (object $event) {
                if ($event instanceof RenderedTreeFinalizationEvent) {
                    $event->replaceTree([
                        ...$event->tree(),
                        new RenderedElement('added-id', 'text', ['title' => 'added-title']),
                    ]);
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

        static::assertSame(['root-id', 'added-id'], $this->collectRenderedIds($result->tree));
        static::assertSame('added-title', $this->renderedElement($result->tree, 'added-id')->properties['title']);
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
        static::assertSame(['redistributor-id', 'consumer-id'], $this->collectRenderedIds($result->tree));
        static::assertSame('product-payload', $this->renderedElement($result->tree, 'consumer-id')->properties['product']);
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

        static::assertSame(['target-id'], $this->collectRenderedIds($result->tree));
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

        $elements = $result->tree;
        static::assertSame($pageData, $elements[0]->properties['language']);
    }

    /**
     * The two finishing steps each early-return when their own scaffolding field is inert, so with only one
     * field live a swap of the two statements changes nothing and no other test in this file sets both. Run
     * in the inverted order the extract reduces the virtual-root-headed forest to the bare target, and the
     * unwrap then looks for a `__page_roots__` slot the target does not have and throws.
     */
    #[TestDox('unwraps the virtual root before extracting the partial-render target')]
    public function testUnwrapsTheVirtualRootBeforeExtractingThePartialTarget(): void
    {
        $target = StoredElementBuilder::create('text', 'target-id')
            ->withConsumer('product', ContextType::Single)
            ->build();
        // The root consumes too, so no element between the target and the virtual root is a context root and
        // the prune keeps the chain all the way up to the wrapper.
        $root = StoredElementBuilder::create('section', 'root-id')
            ->withConsumer('language', ContextType::Single)
            ->withSlot('default', [
                $target,
                StoredElementBuilder::create('text', 'sibling-id')->build(),
            ])
            ->build();
        $layout = $this->createSingleRootLayout($root);
        $specification = new RenderingSpecification(
            [new DataRequirement('language', 'language', new LanguageLoaderConfig())],
            PlaceholderValues::from([]),
            new Request(),
            'target-id'
        );

        $wrapper = new VirtualRootWrapper();

        // Fixture guard: without page-level data requirements the pipeline never wraps, and the unwrap step
        // is inert.
        static::assertTrue($wrapper->requiresWrapping($specification, $layout->elements));

        $preparation = (new StoredTreePreparer(
            $wrapper,
            new PartialRenderer(new ElementTreePruner(), new ContextDependencyAnalyzer(), new SubTreeExtractor()),
            static::createStub(DataLoaderConfigSerializerProvider::class),
        ))->prepare($layout->elements, $specification, RenderingMode::SKELETON);

        // Fixture guard, and what makes the order observable at all: both finishing steps are live, because
        // the prune left the virtual root heading the forest and the target it extracts is still under it.
        static::assertTrue($preparation->scaffolding->virtualRootSurvivedPrune);
        static::assertSame('target-id', $preparation->scaffolding->extractTargetId);
        static::assertSame(
            [VirtualRootWrapper::VIRTUAL_ROOT_ID, 'root-id', 'target-id'],
            $this->collectStoredIds($preparation->tree)
        );

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $result = $this->createPipeline()->load(
            $layout,
            $specification,
            new RenderingCacheContext(),
            RenderingMode::SKELETON,
            false,
            Generator::generateSalesChannelContext()
        );

        static::assertSame(['target-id'], $this->collectRenderedIds($result->tree));
        static::assertSame('text', $result->tree[0]->component);
    }

    #[DataProvider('supportsRenderingModeProvider')]
    #[TestDox('dispatches preparation and rendered-tree finalization lifecycle events in order in either rendering mode')]
    public function testLoadDispatchesLifecycleEventsInBothModes(RenderingMode $mode): void
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

        $pipeline->load($layout, $specification, new RenderingCacheContext(), $mode, false, Generator::generateSalesChannelContext());

        static::assertSame([ContentTreePreparationEvent::class, RenderedTreeFinalizationEvent::class], $dispatchedEvents);
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

        static::assertSame('product-payload', $this->renderedElement($result->tree, 'consumer-id')->properties['product']);
    }

    #[TestDox('serves an empty forest when a preparation subscriber replaces the tree with none')]
    public function testLoadServesAnEmptyForestWhenPreparationLeavesNoElements(): void
    {
        $layout = $this->createSingleRootLayout(StoredElementBuilder::create('text', 'root-id')->build());

        // Fixture guard: the loaded layout is not already empty, so an empty result can only come from the
        // forest the subscriber handed back.
        static::assertSame(['root-id'], $this->collectStoredIds($layout->elements));

        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            static function (object $event) {
                if ($event instanceof ContentTreePreparationEvent) {
                    $event->replaceTree([]);
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

        // What this discriminates: the virtual-root unwrap's early return on an inert scaffolding. Without it
        // the unwrap reads element zero of this empty forest and the render dies on an undefined offset.
        static::assertSame([], $result->tree);
    }

    #[DataProvider('supportsRenderingModeProvider')]
    #[TestDox('rejects a stored forest that repeats an element id across two roots, in either rendering mode')]
    public function testLoadRejectsARepeatedStoredElementId(RenderingMode $mode): void
    {
        $layout = $this->createTwinRootLayout('twin-id');

        // Fixture guard: the twins share an id and differ in everything else, so a passing run means the
        // id collision was detected rather than two indistinguishable nodes collapsing into one.
        $inRootA = $this->findStoredChild($layout->elements[0], 'twin-id');
        $inRootB = $this->findStoredChild($layout->elements[1], 'twin-id');
        static::assertNotNull($inRootA);
        static::assertNotNull($inRootB);
        static::assertSame('in-root-a', $inRootA->property('title')?->asString());
        static::assertSame('in-root-b', $inRootB->property('title')?->asString());

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $this->assertLoadFailsWithDuplicateId(
            $layout,
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            $mode,
            'twin-id'
        );
    }

    #[TestDox('rejects twins straddling two roots on a partial render, which the finished forest no longer shows')]
    public function testLoadRejectsRepeatedStoredIdsStraddlingTwoRootsOnAPartialRender(): void
    {
        $layout = $this->createTwinRootLayout('twin-id');
        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request(), 'twin-id');

        // Fixture guard, and what this test discriminates: the prune runs on EVERY root and keeps a survivor
        // from each one that holds the target, so both twins come through it (each as its own root here, the
        // twin needing no ancestor context). The later extract then returns the first match and discards the
        // other, so the FINISHED forest carries exactly one node under that id — a guard placed there sees
        // nothing wrong and serves one of two ambiguous elements. So this test pins "a check runs before the
        // extract", and no more: a check reading the post-prune tree still passes it. What pins the check to
        // the PRE-prune forest is testLoadRejectsARepeatedStoredIdWhoseTwinThePruneDiscards below.
        $pruned = (new PartialRenderer(new ElementTreePruner(), new ContextDependencyAnalyzer(), new SubTreeExtractor()))
            ->pruneToTarget($layout->elements, 'twin-id');
        static::assertSame(['twin-id', 'twin-id'], $this->collectStoredIds($pruned));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $this->assertLoadFailsWithDuplicateId($layout, $specification, RenderingMode::FULL, 'twin-id');
    }

    #[TestDox('rejects a repeated element id whose twin the partial prune discards, which the pruned tree no longer shows')]
    public function testLoadRejectsARepeatedStoredIdWhoseTwinThePruneDiscards(): void
    {
        $layout = RenderableLayout::create(
            LayoutReference::create($this->ids->get('layout'), 'Discarded Twin Layout', '1.0'),
            [
                StoredElementBuilder::create('section', 'target-id')
                    ->withSlot('default', [
                        StoredElementBuilder::create('text', 'twin-id')
                            ->withProperty('title', 'under-the-target')
                            ->build(),
                    ])
                    ->build(),
                StoredElementBuilder::create('text', 'twin-id')
                    ->withProperty('title', 'in-the-discarded-root')
                    ->build(),
            ]
        );
        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request(), 'target-id');

        // Fixture guard: the pre-prune forest carries TWO twins, sharing an id and differing in the one
        // property `text` declares, so a pass means the collision was detected rather than two
        // indistinguishable nodes collapsing into one.
        static::assertSame(['target-id', 'twin-id', 'twin-id'], $this->collectStoredIds($layout->elements));

        // Fixture guard, and the whole point of this test: the extract target lives in one root only, so the
        // prune drops the OTHER root wholesale and the post-prune tree carries exactly ONE twin. A check
        // reading `$preparation->tree` — or running anywhere after the prune — sees a well-formed forest and
        // serves one of two ambiguous elements. Only a check on the pre-prune forest fails this render.
        $pruned = (new PartialRenderer(new ElementTreePruner(), new ContextDependencyAnalyzer(), new SubTreeExtractor()))
            ->pruneToTarget($layout->elements, 'target-id');
        static::assertSame(['target-id', 'twin-id'], $this->collectStoredIds($pruned));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $this->assertLoadFailsWithDuplicateId($layout, $specification, RenderingMode::FULL, 'twin-id');
    }

    #[DataProvider('supportsRenderingModeProvider')]
    #[TestDox('rejects a repeated element id a finalization subscriber put into the forest, in either rendering mode')]
    public function testLoadRejectsARepeatedElementIdIntroducedDuringFinalization(RenderingMode $mode): void
    {
        $layout = $this->createSingleRootLayout(StoredElementBuilder::create('text', 'root-id')->build());

        // Fixture guard: the stored forest repeats nothing, so the check over it cannot be what fires — the
        // duplicate exists only in the forest the subscriber hands back.
        static::assertSame(['root-id'], $this->collectStoredIds($layout->elements));

        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            static function (object $event) {
                if ($event instanceof RenderedTreeFinalizationEvent) {
                    // The twins share an id and differ in their title, so the throw can only come from the
                    // id collision and not from two identical nodes being folded together.
                    $event->replaceTree([
                        new RenderedElement('twin-id', 'text', ['title' => 'first-twin']),
                        new RenderedElement('twin-id', 'text', ['title' => 'second-twin']),
                    ]);
                }

                return $event;
            }
        );

        $this->assertLoadFailsWithDuplicateId(
            $layout,
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            $mode,
            'twin-id'
        );
    }

    #[TestDox('rejects a repeated element id a finalization subscriber buried inside a slot')]
    public function testLoadRejectsARepeatedElementIdNestedBySubscriber(): void
    {
        $layout = $this->createSingleRootLayout(StoredElementBuilder::create('text', 'root-id')->build());

        // Fixture guard: the stored forest repeats nothing, so the check over it cannot be what fires.
        static::assertSame(['root-id'], $this->collectStoredIds($layout->elements));

        $this->eventDispatcher->method('dispatch')->willReturnCallback(
            static function (object $event) {
                if ($event instanceof RenderedTreeFinalizationEvent) {
                    // The second twin sits two slots deep, so only a walk that descends into `slots`
                    // reaches it: a check that compared root ids alone would serve this forest. The two
                    // share an id and differ in their title, so the throw is the collision and not two
                    // identical nodes folding together.
                    $event->replaceTree([
                        new RenderedElement('twin-id', 'text', ['title' => 'the-root-twin']),
                        new RenderedElement('holder-id', 'section', [], [
                            'default' => [
                                new RenderedElement('inner-id', 'section', [], [
                                    'default' => [
                                        new RenderedElement('twin-id', 'text', ['title' => 'the-buried-twin']),
                                    ],
                                ]),
                            ],
                        ]),
                    ]);
                }

                return $event;
            }
        );

        $this->assertLoadFailsWithDuplicateId(
            $layout,
            new RenderingSpecification([], PlaceholderValues::from([]), new Request()),
            RenderingMode::FULL,
            'twin-id'
        );
    }

    /**
     * The wrapper element is minted during rendering and never reaches a stored tree, so the write gate can
     * never see this collision: a layout authored under the reserved id passes every write and then fails
     * every render that wraps. This test is what pins the duplicate check to a position AFTER the wrap.
     */
    #[TestDox('rejects a stored element authored under the reserved virtual-root id')]
    public function testLoadRejectsAStoredElementAuthoredUnderTheReservedVirtualRootId(): void
    {
        $layout = $this->createSingleRootLayout(
            StoredElementBuilder::create('section', VirtualRootWrapper::VIRTUAL_ROOT_ID)->build()
        );
        $specification = new RenderingSpecification(
            [new DataRequirement('language', 'language', new LanguageLoaderConfig())],
            PlaceholderValues::from([]),
            new Request()
        );

        // Fixture guard: without a page-level data requirement the pipeline never wraps, nothing mints a
        // second element under the reserved id, and there is no collision to reject.
        static::assertTrue((new VirtualRootWrapper())->requiresWrapping($specification, $layout->elements));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        $this->assertLoadFailsWithDuplicateId(
            $layout,
            $specification,
            RenderingMode::FULL,
            VirtualRootWrapper::VIRTUAL_ROOT_ID
        );
    }

    #[TestDox('rejects a partial render whose target element is in no root of the layout')]
    public function testLoadRejectsAPartialRenderWhoseTargetIsInNoRoot(): void
    {
        $layout = $this->createPartialRenderLayout();
        $specification = new RenderingSpecification([], PlaceholderValues::from([]), new Request(), 'absent-id');

        // Fixture guard: the target really is in no root, so the prune leaves an empty forest and the extract
        // is the step that has to report it.
        static::assertNotContains('absent-id', $this->collectStoredIds($layout->elements));

        $this->eventDispatcher->method('dispatch')->willReturnArgument(0);

        try {
            $this->createPipeline()->load(
                $layout,
                $specification,
                new RenderingCacheContext(),
                RenderingMode::FULL,
                false,
                Generator::generateSalesChannelContext()
            );
            static::fail('Expected ContentSystemException was not thrown.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::ELEMENT_NOT_FOUND, $exception->getErrorCode());
            static::assertStringContainsString(
                'Element with ID "absent-id" not found in layout',
                $exception->getMessage()
            );
        }
    }

    /**
     * @return iterable<string, array{RenderingMode}>
     */
    public static function supportsRenderingModeProvider(): iterable
    {
        yield 'SKELETON mode' => [RenderingMode::SKELETON];
        yield 'FULL mode' => [RenderingMode::FULL];
    }

    /**
     * @return iterable<string, array{RenderingMode, array<string, array<string, mixed>>, list<string>}>
     */
    public static function indexesRenderedPropertiesProvider(): iterable
    {
        yield 'SKELETON mode indexes nothing, because it mints no property' => [RenderingMode::SKELETON, [], []];
        yield 'FULL mode indexes the rendered property of the root' => [
            RenderingMode::FULL,
            ['root-id' => ['title' => 'authored-title']],
            ['authored-title'],
        ];
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function derivesProviderKeyOnlyWhereAliasedProvider(): iterable
    {
        yield 'no alias keeps the redistributed key' => [null, 'featuredProduct'];
        yield 'alias renames the key for children' => ['product', 'product'];
    }

    private function createPipeline(): ContentPipeline
    {
        return new ContentPipeline(
            $this->eventDispatcher,
            new StoredTreePreparer(
                new VirtualRootWrapper(),
                new PartialRenderer(new ElementTreePruner(), new ContextDependencyAnalyzer(), new SubTreeExtractor()),
                $this->configSerializers,
            ),
            new WiringPlanner(new ProviderDeliveryKeyResolver()),
            $this->lowering,
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

        $this->configSerializers = new DataLoaderConfigSerializerProvider(new ServiceLocator($serializers));

        return new ElementLowering(
            new ElementDataResolver(
                new DataLoaderProvider(new ServiceLocator($factories)),
                new LoaderInputResolver(),
                new LoaderValueIdentityFactory(
                    $this->configSerializers,
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

    /**
     * Two roots, each holding one element under the same id, differing in the one property `text` declares.
     * Nothing between this array literal and `load()` validates ids, so the forest reaches the pipeline
     * corrupt exactly as a raw-SQL write or a preparation subscriber would leave it.
     */
    private function createTwinRootLayout(string $twinId): RenderableLayout
    {
        return RenderableLayout::create(
            LayoutReference::create($this->ids->get('layout'), 'Twin Layout', '1.0'),
            [
                StoredElementBuilder::create('section', 'root-a')
                    ->withSlot('default', [
                        StoredElementBuilder::create('text', $twinId)->withProperty('title', 'in-root-a')->build(),
                    ])
                    ->build(),
                StoredElementBuilder::create('section', 'root-b')
                    ->withSlot('default', [
                        StoredElementBuilder::create('text', $twinId)->withProperty('title', 'in-root-b')->build(),
                    ])
                    ->build(),
            ]
        );
    }

    private function assertLoadFailsWithDuplicateId(
        RenderableLayout $layout,
        RenderingSpecification $specification,
        RenderingMode $mode,
        string $elementId,
    ): void {
        try {
            $this->createPipeline()->load(
                $layout,
                $specification,
                new RenderingCacheContext(),
                $mode,
                false,
                Generator::generateSalesChannelContext()
            );
            static::fail('Expected ContentSystemException was not thrown.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::DUPLICATE_ELEMENT_ID, $exception->getErrorCode());
            static::assertStringContainsString(
                \sprintf('element ID "%s" appears more than once', $elementId),
                $exception->getMessage()
            );
        }
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
     * @param list<RenderedElement> $elements
     */
    private function renderedElement(array $elements, string $childId): RenderedElement
    {
        $found = $this->findRenderedElement($elements, $childId);
        static::assertNotNull($found, \sprintf('No rendered element with id "%s" in the result.', $childId));

        return $found;
    }

    /**
     * @param list<RenderedElement> $elements
     */
    private function findRenderedElement(array $elements, string $childId): ?RenderedElement
    {
        foreach ($elements as $element) {
            if ($element->id === $childId) {
                return $element;
            }

            foreach ($element->slots as $children) {
                $found = $this->findRenderedElement($children, $childId);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
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
