<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDeliveryResolver;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDistributor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubContextStruct;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextDeliveryResolver::class)]
class ContextDeliveryResolverTest extends TestCase
{
    #[TestDox('delivers a parent value to its own children')]
    public function testDeliversFromParentToItsChildren(): void
    {
        $child = $this->consumer('child-1', 'product');
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withProperty('product', 'product-data')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$child])
            ->build();

        $index = $this->resolver()->resolve([$root], [], []);

        static::assertSame(['product' => 'product-data'], $index->all()['child-1']->context);
    }

    #[TestDox('records an entry for every element, roots and non-receivers included')]
    public function testIndexIsTotalOverTheForest(): void
    {
        $bystander = StoredElementBuilder::create('Sw:Text', 'child-2')->build();
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withProperty('product', 'product-data')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$this->consumer('child-1', 'product'), $bystander])
            ->build();

        $index = $this->resolver()->resolve([$root], [], []);

        static::assertSame(['root-1', 'child-1', 'child-2'], array_keys($index->all()));
        static::assertTrue($index->all()['root-1']->isEmpty());
        static::assertTrue($index->all()['child-2']->isEmpty());
    }

    #[TestDox('covers every root of a multi-root forest')]
    public function testEveryRootOfTheForestIsWalked(): void
    {
        $forest = [
            $this->providerRoot('root-1', 'child-1', 'first-data'),
            $this->providerRoot('root-2', 'child-2', 'second-data'),
        ];

        $index = $this->resolver()->resolve($forest, [], []);

        static::assertSame(['product' => 'first-data'], $index->all()['child-1']->context);
        static::assertSame(['product' => 'second-data'], $index->all()['child-2']->context);
    }

    /**
     * The reason the walk is top-down. The middle element re-provides under `product` the value it received
     * under `product`, so its own delivery has to be in its working map before it distributes. Two hops is
     * the shortest chain that shows it: one hop works whatever the order.
     */
    #[TestDox('passes a received value on through a re-providing container, two hops down')]
    public function testChainedRedistributionReachesTheSecondHop(): void
    {
        $grandchild = $this->consumer('grandchild-1', 'product');
        $middle = StoredElementBuilder::create('Sw:Section', 'child-1')
            ->withConsumer('product', ContextType::Single)
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$grandchild])
            ->build();
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withProperty('product', 'product-data')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$middle])
            ->build();

        $index = $this->resolver()->resolve([$root], [], []);

        static::assertSame(['product' => 'product-data'], $index->all()['child-1']->context);
        static::assertSame(['product' => 'product-data'], $index->all()['grandchild-1']->context);
    }

    /**
     * The flattening contract `ContextDistributor` depends on and cannot check. Indexed distribution with
     * three distinct items is what makes the order observable — a broadcast fixture would pass under any
     * flattening, because every position carries the same value.
     */
    #[TestDox('flattens children slot by slot, in index order within each slot')]
    public function testChildrenAreFlattenedInSlotThenIndexOrder(): void
    {
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withProperty('items', ['first-item', 'second-item', 'third-item'])
            ->withProvider('items', IndexedDistributionConfig::simple())
            ->withSlot('left', [$this->consumer('child-1', 'items'), $this->consumer('child-2', 'items')])
            ->withSlot('right', [$this->consumer('child-3', 'items')])
            ->build();

        $index = $this->resolver()->resolve([$root], [], []);

        static::assertSame(['items' => 'first-item'], $index->all()['child-1']->context);
        static::assertSame(['items' => 'second-item'], $index->all()['child-2']->context);
        static::assertSame(['items' => 'third-item'], $index->all()['child-3']->context);
    }

    #[TestDox('distributes a loader resolved value when the provider key names one')]
    public function testLoaderValuesAreAvailableToDistribute(): void
    {
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$this->consumer('child-1', 'product')])
            ->build();

        $index = $this->resolver()->resolve([$root], ['root-1' => ['product' => 'loaded-data']], []);

        static::assertSame(['product' => 'loaded-data'], $index->all()['child-1']->context);
    }

    #[TestDox('prefers a loader resolved value over the stored value of the same key')]
    public function testLoaderValueOutranksTheStoredValue(): void
    {
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withProperty('product', 'stored-data')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$this->consumer('child-1', 'product')])
            ->build();

        $index = $this->resolver()->resolve([$root], ['root-1' => ['product' => 'loaded-data']], []);

        static::assertSame(['product' => 'loaded-data'], $index->all()['child-1']->context);
    }

    #[TestDox('prefers the context a parent received over its own stored value of the same key')]
    public function testReceivedContextOutranksTheStoredValue(): void
    {
        $grandchild = $this->consumer('grandchild-1', 'product');
        $middle = StoredElementBuilder::create('Sw:Section', 'child-1')
            ->withProperty('product', 'stored-data')
            ->withConsumer('product', ContextType::Single)
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$grandchild])
            ->build();
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withProperty('product', 'inherited-data')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$middle])
            ->build();

        $index = $this->resolver()->resolve([$root], [], []);

        static::assertSame(['product' => 'inherited-data'], $index->all()['grandchild-1']->context);
    }

    /**
     * A loader that ran and found nothing writes a present null over the stored value, and the null provider
     * gate then distributes nothing. The live hydrator instead leaves the stored value in place, so this is a
     * deliberate divergence: the stored value under a requirement key is a raw id the loader just failed to
     * resolve, and delivering it would put that id onto a CHILD's rendered element through the delivered
     * context tier — which is the outcome the declared-reference rule exists to prevent, one element over.
     */
    #[TestDox('distributes nothing when a loader found nothing under a key the element also stores')]
    public function testALoaderNullSuppressesTheStoredProviderValue(): void
    {
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withProperty('product', 'stored-product-id')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$this->consumer('child-1', 'product')])
            ->build();

        $index = $this->resolver()->resolve([$root], ['root-1' => ['product' => null]], []);

        static::assertSame([], $index->all()['child-1']->context);
    }

    /**
     * Keyed selection reads the consumer's STORED properties only, so a `keyProperty` that reaches the child
     * as delivered context selects nothing. The live path reads the child's post-load map and would select on
     * it, so the two implementations legitimately disagree here and this case is deliberately absent from the
     * differential harness.
     */
    #[TestDox('does not select a keyed consumer on a key property that arrived as delivered context')]
    public function testKeyedSelectionIgnoresADeliveredKeyProperty(): void
    {
        $child = StoredElementBuilder::create('Sw:Box', 'child-1')
            ->withConsumer('data_key', ContextType::Single)
            ->withConsumer('items', ContextType::Single)
            ->build();
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withProperty('data_key', 'present')
            ->withProperty('items', ['present' => 'present-item'])
            ->withProvider('data_key', BroadcastDistributionConfig::simple())
            ->withProvider('items', KeyedDistributionConfig::simple())
            ->withSlot('main', [$child])
            ->build();

        $index = $this->resolver()->resolve([$root], [], []);

        static::assertSame(['data_key' => 'present', 'items' => null], $index->all()['child-1']->context);
    }

    #[TestDox('carries the distribution referenced keys of a keyed provider through to the index')]
    public function testDistributionReferencedKeysReachTheIndex(): void
    {
        $child = StoredElementBuilder::create('Sw:Box', 'child-1')
            ->withProperty('data_key', 'present')
            ->withConsumer('items', ContextType::Single)
            ->build();
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withProperty('items', ['present' => 'present-item'])
            ->withProvider('items', KeyedDistributionConfig::simple())
            ->withSlot('main', [$child])
            ->build();

        $index = $this->resolver()->resolve([$root], [], []);

        static::assertSame(['data_key'], $index->all()['child-1']->distributionReferencedKeys);
    }

    #[TestDox('leaves the walked elements untouched')]
    public function testWalkedElementsAreNotMutated(): void
    {
        $child = $this->consumer('child-1', 'product');
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withProperty('product', 'product-data')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$child])
            ->build();

        $this->resolver()->resolve([$root], [], []);

        static::assertSame([], $child->properties());
        static::assertSame(['product' => 'product-data'], array_map(
            static fn ($value): mixed => $value->jsonSerialize(),
            $root->properties()
        ));
    }

    #[TestDox('returns an empty index for an empty forest')]
    public function testEmptyForestYieldsAnEmptyIndex(): void
    {
        $index = $this->resolver()->resolve([], [], []);

        static::assertSame([], $index->all());
    }

    #[TestDox('reports an element id it never walked as absent rather than as having received nothing')]
    public function testAnIdOutsideTheForestIsAbsentFromTheIndex(): void
    {
        $index = $this->resolver()->resolve([$this->providerRoot('root-1', 'child-1', 'product-data')], [], []);

        static::assertTrue($index->has('child-1'));
        static::assertFalse($index->has('not-in-this-forest'));
    }

    #[TestDox('throws when a required dotted consumer receives a provider value that is not a Struct')]
    public function testRequiredDottedConsumerRejectsANonStructProviderValue(): void
    {
        $child = StoredElementBuilder::create('Sw:Box', 'child-1')
            ->withConsumer('product.name', ContextType::Single, required: true)
            ->build();
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withProperty('product', 'not-a-struct')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$child])
            ->build();

        $this->expectExceptionObject(
            ContentSystemException::contextPathNotResolvable('product.name', 'child-1', 'Context data is not a Struct instance')
        );

        $this->resolver()->resolve([$root], [], []);
    }

    /**
     * The depth claim, and the reason the ambient map is an argument rather than a tree lookup: nothing
     * between the root and the grandchild is wired, so a mechanism that walked the tree to find root context
     * would have to stop at the first unwired ancestor and deliver nothing here.
     */
    #[TestDox('delivers a root-ambient value to a root-scoped consumer three levels down with no wiring in between')]
    public function testRootScopedConsumerReceivesAmbientContextAtDepth(): void
    {
        $grandchild = $this->rootScopedConsumer('grandchild-1', 'language');
        $middle = StoredElementBuilder::create('Sw:Section', 'child-1')
            ->withSlot('main', [$grandchild])
            ->build();
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withSlot('main', [$middle])
            ->build();

        // Fixture guard: no element on the path declares a provider, so nothing can be handed down a hop
        // at a time and the ambient argument is the only possible source.
        static::assertSame([], $root->contextDefinitions->getAllProviders());
        static::assertSame([], $middle->contextDefinitions->getAllProviders());

        $index = $this->resolver()->resolve([$root], [], ['language' => 'page-language']);

        static::assertSame(['language' => 'page-language'], $index->all()['grandchild-1']->context);
        static::assertSame([], $index->all()['child-1']->context);
    }

    /**
     * The exclusivity pin. Both consumers name the same ambient key and differ only in scope, so a delivery
     * rule that ignored scope would fill both and this test would report the same map twice.
     */
    #[TestDox('fills a root-scoped consumer from the ambient map and leaves a parent-scope consumer of the same key empty')]
    public function testParentScopeConsumerReceivesNothingFromTheAmbientMap(): void
    {
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withSlot('main', [
                $this->rootScopedConsumer('root-scoped-1', 'language'),
                $this->consumer('parent-scoped-1', 'language'),
            ])
            ->build();

        $index = $this->resolver()->resolve([$root], [], ['language' => 'page-language']);

        static::assertSame(['language' => 'page-language'], $index->all()['root-scoped-1']->context);
        static::assertSame([], $index->all()['parent-scoped-1']->context);
    }

    #[TestDox('resolves a dotted root-scoped consumer key through the ambient struct')]
    public function testDottedRootScopedConsumerResolvesThroughTheAmbientStruct(): void
    {
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withSlot('main', [$this->rootScopedConsumer('child-1', 'product.cover')])
            ->build();

        $index = $this->resolver()->resolve(
            [$root],
            [],
            ['product' => new StubContextStruct('page-cover')]
        );

        static::assertSame(['product.cover' => 'page-cover'], $index->all()['child-1']->context);
    }

    #[TestDox('throws naming the element when a required dotted root-scoped consumer cannot resolve its path')]
    public function testRequiredDottedRootScopedConsumerRejectsANonStructAmbientValue(): void
    {
        $child = StoredElementBuilder::create('Sw:Box', 'child-1')
            ->withConsumer('product.cover', ContextType::Single, required: true, scope: ConsumerScope::Root)
            ->build();
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withSlot('main', [$child])
            ->build();

        $this->expectExceptionObject(ContentSystemException::contextPathNotResolvable(
            'product.cover',
            'child-1',
            'Context data is not a Struct instance'
        ));

        $this->resolver()->resolve([$root], [], ['product' => 'not-a-struct']);
    }

    /**
     * The optional twin of the rejection above: a dotted path needs a Struct to traverse, and an optional
     * consumer that cannot get one takes a PRESENT null (a resolution ran and found nothing), unlike the
     * ambient null below, which writes no key.
     */
    #[TestDox('delivers a present null to an optional dotted root-scoped consumer over a non-Struct ambient value')]
    public function testOptionalDottedRootScopedConsumerTakesANullOverANonStructAmbientValue(): void
    {
        $child = StoredElementBuilder::create('Sw:Box', 'child-1')
            ->withConsumer('product.cover', ContextType::Single, required: false, scope: ConsumerScope::Root)
            ->build();
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withSlot('main', [$child])
            ->build();

        $index = $this->resolver()->resolve([$root], [], ['product' => 'not-a-struct']);

        static::assertSame(['product.cover' => null], $index->all()['child-1']->context);
    }

    /**
     * Present-null and key-absent are different states, so this asserts the WHOLE map rather than that the
     * key holds null: a rendered null means a resolution ran and found nothing, and an ambient null must not
     * be able to manufacture one.
     */
    #[TestDox('writes no key at all when the ambient value under a root-scoped consumer key is null')]
    public function testAmbientNullDeliversNothingAndWritesNoKey(): void
    {
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withSlot('main', [$this->rootScopedConsumer('child-1', 'language')])
            ->build();

        $index = $this->resolver()->resolve([$root], [], ['language' => null]);

        static::assertSame([], $index->all()['child-1']->context);
    }

    #[TestDox('lands a root-ambient value under the property alias when the root-scoped consumer declares one')]
    public function testRootScopedConsumerLandsUnderItsPropertyAlias(): void
    {
        $child = StoredElementBuilder::create('Sw:Box', 'child-1')
            ->withConsumer('language', ContextType::Single, propertyAlias: 'pageLanguage', scope: ConsumerScope::Root)
            ->build();
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withSlot('main', [$child])
            ->build();

        $index = $this->resolver()->resolve([$root], [], ['language' => 'page-language']);

        static::assertSame(['pageLanguage' => 'page-language'], $index->all()['child-1']->context);
    }

    /**
     * The ordering pin: the root-scoped overlay is applied to an element's delivery BEFORE its working map is
     * read, so a value it received ambiently is in the map its own providers distribute from. Apply the
     * overlay after — or record the pre-overlay delivery and distribute from that — and the middle element
     * hands its child nothing, while its own map still reads correctly.
     */
    #[TestDox('lets an element re-provide a value it received through its own root-scoped consumer')]
    public function testARootDeliveredValueEntersTheWorkingMapItsProvidersDistributeFrom(): void
    {
        $grandchild = $this->consumer('grandchild-1', 'language');
        $middle = StoredElementBuilder::create('Sw:Section', 'child-1')
            ->withConsumer('language', ContextType::Single, scope: ConsumerScope::Root)
            ->withProvider('language', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$grandchild])
            ->build();
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withSlot('main', [$middle])
            ->build();

        // Fixture guard: the middle element stores nothing under the key it provides, so the only value it
        // can hand on is the one the ambient overlay put into its working map.
        static::assertNull($middle->property('language'));

        $index = $this->resolver()->resolve([$root], [], ['language' => 'page-language']);

        static::assertSame(['language' => 'page-language'], $index->all()['child-1']->context);
        static::assertSame(['language' => 'page-language'], $index->all()['grandchild-1']->context);
    }

    /**
     * The overlay replaces the delivery object, so everything the parent's distribution round put on it
     * has to survive the swap. `distributionReferencedKeys` is the field that can silently vanish: it
     * decides which stored keys the child renders, and an overlay that rebuilt the delivery from the
     * context map alone would drop it while every delivered value still looked right. The child here
     * needs BOTH halves — a keyed distribution names `data_key` against it, and a root-scoped consumer
     * overlays it — because a child with only one of the two never exercises the swap.
     */
    #[TestDox('keeps the distribution referenced keys of a child whose delivery the root overlay replaced')]
    public function testTheRootOverlayPreservesDistributionReferencedKeys(): void
    {
        $child = StoredElementBuilder::create('Sw:Box', 'child-1')
            ->withProperty('data_key', 'present')
            ->withConsumer('items', ContextType::Single)
            ->withConsumer('language', ContextType::Single, scope: ConsumerScope::Root)
            ->build();
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withProperty('items', ['present' => 'present-item'])
            ->withProvider('items', KeyedDistributionConfig::simple())
            ->withSlot('main', [$child])
            ->build();

        $delivery = $this->resolver()->resolve([$root], [], ['language' => 'page-language'])->all()['child-1'];

        // Fixture guard: the overlay really did run, so the delivery really was replaced.
        static::assertSame(
            ['items' => 'present-item', 'language' => 'page-language'],
            $delivery->context
        );
        static::assertSame(['data_key'], $delivery->distributionReferencedKeys);
        static::assertSame('child-1', $delivery->elementId);
    }

    #[TestDox('hands an exact-key root delivery the same instance the ambient map holds')]
    public function testExactKeyRootDeliveryHandsOnTheSameInstance(): void
    {
        $ambientValue = new StubContextStruct('page-cover');
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withSlot('main', [$this->rootScopedConsumer('child-1', 'product')])
            ->build();

        $index = $this->resolver()->resolve([$root], [], ['product' => $ambientValue]);

        static::assertSame($ambientValue, $index->all()['child-1']->context['product']);
    }

    private function resolver(): ContextDeliveryResolver
    {
        return new ContextDeliveryResolver(
            new ContextDistributor(new ContextPathResolver()),
            new ContextPathResolver()
        );
    }

    private function rootScopedConsumer(string $id, string $contextKey): StoredElement
    {
        return StoredElementBuilder::create('Sw:Box', $id)
            ->withConsumer($contextKey, ContextType::Single, scope: ConsumerScope::Root)
            ->build();
    }

    private function consumer(string $id, string $contextKey): StoredElement
    {
        return StoredElementBuilder::create('Sw:Box', $id)
            ->withConsumer($contextKey, ContextType::Single)
            ->build();
    }

    private function providerRoot(string $rootId, string $childId, string $value): StoredElement
    {
        return StoredElementBuilder::create('Sw:Section', $rootId)
            ->withProperty('product', $value)
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$this->consumer($childId, 'product')])
            ->build();
    }
}
