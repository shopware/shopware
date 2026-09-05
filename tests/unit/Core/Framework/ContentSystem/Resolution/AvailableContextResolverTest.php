<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ProviderDeliveryKeyResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Resolution\AvailableContextResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\ElementResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AvailableContextResolver::class)]
class AvailableContextResolverTest extends TestCase
{
    #[TestDox('returns the bound source root-ambient context for a top-level element')]
    public function testTopLevelReceivesRootAmbient(): void
    {
        $root = new StoredElement('root-1', 'Sw:Block');

        $rootContext = $this->rootAmbientProductContext();

        static::assertSame($rootContext, $this->resolver()->resolve('root-1', [$root], $rootContext));
    }

    #[TestDox('returns nothing for a top-level element whose source exposes no root-ambient context (header/footer)')]
    public function testTopLevelWithoutRootAmbientReceivesNothing(): void
    {
        $root = new StoredElement('root-1', 'Sw:Block');

        static::assertSame([], $this->resolver()->resolve('root-1', [$root], []));
    }

    #[TestDox('resolves ancestor provider context with the FQCN from the provider type spec for a nested element')]
    public function testNestedReceivesAncestorProvider(): void
    {
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement(
            'root-1',
            'Sw:Provider',
            [],
            [],
            ['content' => [$child]],
            new ContextDefinitions(
                ['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                [],
            ),
        );

        $available = $this->resolver()->resolve('child-1', [$root], []);

        static::assertCount(1, $available);
        static::assertSame('product', $available[0]->contextKey);
        static::assertSame(SalesChannelProductEntity::class, $available[0]->fqcn);
        static::assertSame('root-1', $available[0]->providerElementId);
        static::assertSame(DistributionStrategy::Broadcast, $available[0]->distribution);
        // An ancestor's exposure is element-provided, never root-ambient: the flag is what the scope-aware
        // diagnostics and the origin split downstream read, so a true here would offer it to root-scoped
        // consumers the runtime never delivers to.
        static::assertFalse($available[0]->root);
    }

    #[TestDox('exposes a backed ancestor provider under its consumer alias, the key the serving path matches children on')]
    public function testAliasedProviderExposesConsumerAlias(): void
    {
        // Mirrors ContextDistributor's child matching ($config->getConsumerAlias() ?? $contextKey): a provider
        // keyed by the alias at serving must be judged available under the alias at the write gate too.
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement(
            'root-1',
            'Sw:Provider',
            [],
            [],
            ['content' => [$child]],
            new ContextDefinitions(
                ['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::aliased('item'))],
                [],
            ),
        );

        $available = $this->resolver()->resolve('child-1', [$root], []);

        static::assertCount(1, $available);
        static::assertSame('item', $available[0]->contextKey);
        static::assertSame(SalesChannelProductEntity::class, $available[0]->fqcn);
        static::assertSame('root-1', $available[0]->providerElementId);
        static::assertSame(DistributionStrategy::Broadcast, $available[0]->distribution);
    }

    #[TestDox('gives a nested element the same root-ambient context a top-level element gets, with no intermediate wiring')]
    public function testNestedReceivesRootAmbientWithoutIntermediateWiring(): void
    {
        // The uniform formula: one rule for every depth, so the deep element and the top-level element see the
        // same ambient entry even though not one element between them declares any wiring.
        $child = new StoredElement('child-1', 'Sw:Block');
        $intermediate = new StoredElement('level-2', 'Sw:Block', [], [], ['content' => [$child]]);
        $root = new StoredElement('root-1', 'Sw:Block', [], [], ['content' => [$intermediate]]);

        $rootContext = $this->rootAmbientProductContext();

        static::assertSame($rootContext, $this->resolver()->resolve('child-1', [$root], $rootContext));
        static::assertSame($rootContext, $this->resolver()->resolve('root-1', [$root], $rootContext));
    }

    #[TestDox('does not expose a backed ancestor provider past a non-redistributing intermediate')]
    public function testProviderContextStopsAtNonRedistributingIntermediate(): void
    {
        $grandchild = new StoredElement('grandchild-1', 'Sw:Block');
        $child = new StoredElement('child-1', 'Sw:Block', [], [], ['content' => [$grandchild]]);
        $root = new StoredElement(
            'root-1',
            'Sw:Provider',
            [],
            [],
            ['content' => [$child]],
            new ContextDefinitions(
                ['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                [],
            ),
        );

        static::assertSame([], $this->resolver()->resolve('grandchild-1', [$root], []));
    }

    #[TestDox('re-exposes incoming element-provided context through a redistributing intermediate with the inflowing type')]
    public function testRedistributeReExposesIncomingProviderContext(): void
    {
        $tree = $this->providerChain(['product' => new ContextConsumer(ContextType::Single, required: false, redistribute: true)]);

        $available = $this->resolver()->resolve('deep-1', $tree, []);

        static::assertCount(1, $available);
        static::assertSame('product', $available[0]->contextKey);
        static::assertSame(SalesChannelProductEntity::class, $available[0]->fqcn);
        static::assertSame(ContextType::Single, $available[0]->contextType);
        static::assertSame('level-2', $available[0]->providerElementId);
        static::assertSame(DistributionStrategy::Broadcast, $available[0]->distribution);
        // The relay is element-provided too: a redistributing element hands on what it received off the chain
        // under its own address, so the flag stays false on this branch as well.
        static::assertFalse($available[0]->root);
    }

    #[TestDox('remaps the re-exposed key to the consumer alias while keeping the inflowing type')]
    public function testRedistributeConsumerAliasRemapsExposedKey(): void
    {
        $tree = $this->providerChain(['product' => new ContextConsumer(ContextType::Single, required: false, redistribute: true, consumerAlias: 'item')]);

        $available = $this->resolver()->resolve('deep-1', $tree, []);

        static::assertCount(1, $available);
        static::assertSame('item', $available[0]->contextKey);
        static::assertSame(SalesChannelProductEntity::class, $available[0]->fqcn);
    }

    #[TestDox('does not re-expose a redistribute consumer whose key is absent from the incoming context')]
    public function testRedistributeWithoutMatchingIncomingKeyExposesNothing(): void
    {
        // F1 regression guard: a redistribute consumer re-exposes only a key that actually flows into the
        // element, so unconditional re-exposure cannot re-open the over-permissive availability leak. The
        // ancestor's `product` does flow in, so an unconditional re-exposure of `category` would show up here.
        $tree = $this->providerChain(['category' => new ContextConsumer(ContextType::Single, required: false, redistribute: true)]);

        static::assertSame([], $this->resolver()->resolve('deep-1', $tree, []));
    }

    #[TestDox('does not re-expose incoming context through a consumer that does not redistribute')]
    public function testNonRedistributingConsumerDoesNotReExposeIncomingContext(): void
    {
        $tree = $this->providerChain(['product' => new ContextConsumer(ContextType::Single, required: false, redistribute: false)]);

        static::assertSame([], $this->resolver()->resolve('deep-1', $tree, []));
    }

    #[TestDox('gives a deep element the root-ambient entry directly and contributes nothing from the redistribute chain above it')]
    public function testRedistributeChainDoesNotCarryRootAmbientContext(): void
    {
        // A chain never carries root context: both intermediates redistribute `product`, and the key exists
        // only in the root-ambient set, so neither relays anything. What the deep element sees is the ambient
        // entry itself, appended by the uniform formula. Asserting an empty result would be wrong: the formula
        // appends the ambient set at every depth, and a test demanding [] would invite weakening it.
        $deep = new StoredElement('deep-1', 'Sw:Block');
        $level2 = new StoredElement(
            'level-2',
            'Sw:Block',
            [],
            [],
            ['content' => [$deep]],
            new ContextDefinitions([], ['product' => new ContextConsumer(ContextType::Single, required: false, redistribute: true)]),
        );
        $root = new StoredElement(
            'root-1',
            'Sw:Block',
            [],
            [],
            ['content' => [$level2]],
            new ContextDefinitions([], ['product' => new ContextConsumer(ContextType::Single, required: false, redistribute: true)]),
        );

        $rootContext = $this->rootAmbientProductContext();

        $available = $this->resolver()->resolve('deep-1', [$root], $rootContext);

        static::assertSame($rootContext, $available);
        static::assertTrue($available[0]->root);
        static::assertNull($available[0]->providerElementId);
        static::assertSame([], array_values(array_filter(
            $available,
            static fn (ProvidedContext $provided): bool => $provided->providerElementId === 'level-2',
        )));
    }

    #[TestDox('backs an ancestor provider through the root-ambient set and exposes the result as element-provided beside it')]
    public function testAmbientContextBacksAnAncestorProviderExposedAsElementProvided(): void
    {
        // The sanctioned way root-derived data becomes element-provided: the ancestor's own `product` property
        // is filled from the ambient set through its own root-scoped consumer, which is what backs its declared
        // provider, and what it hands downstream carries its own address and root:false. No loader can back the
        // provider here, so the ambient set is the only thing that can, and dropping it from the backing
        // judgment collapses this to the ambient entry alone.
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement(
            'root-1',
            'Sw:Provider',
            [],
            [],
            ['content' => [$child]],
            new ContextDefinitions(
                ['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                ['product' => new ContextConsumer(ContextType::Single, required: true, scope: ConsumerScope::Root)],
            ),
        );

        $available = $this->resolverWithoutLoaderBacking()->resolve('child-1', [$root], $this->rootAmbientProductContext());

        static::assertCount(2, $available);
        static::assertSame('root-1', $available[0]->providerElementId);
        static::assertFalse($available[0]->root);
        static::assertNull($available[1]->providerElementId);
        static::assertTrue($available[1]->root);
    }

    #[TestDox('does not back an ancestor provider from the root-ambient set when that ancestor declares no root-scoped consumer')]
    public function testAmbientContextDoesNotBackAProviderWithoutARootScopedConsumer(): void
    {
        // The negative half of the rule above, and the same fixture minus the consumer. Nothing delivers an
        // ambient value into this ancestor at render time, so its declared provider stays unbacked and the
        // child sees the ambient entry alone. Judging the whole ambient set instead mints a phantom exposure
        // here, which then satisfies a required parent-scope consumer downstream that render never feeds.
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement(
            'root-1',
            'Sw:Provider',
            [],
            [],
            ['content' => [$child]],
            new ContextDefinitions(
                ['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                [],
            ),
        );

        $rootContext = $this->rootAmbientProductContext();

        static::assertSame($rootContext, $this->resolverWithoutLoaderBacking()->resolve('child-1', [$root], $rootContext));
    }

    #[TestDox('does not back an ancestor provider from the root-ambient set through a parent-scoped consumer')]
    public function testAmbientContextDoesNotBackAProviderThroughAParentScopedConsumer(): void
    {
        // The scope split, on the backing side. This consumer lands on the provider's own key and names the
        // ambient key, so both other clauses accept it, and only its scope disqualifies it. The ambient map
        // fills root-scoped consumers alone, so a parent-scoped one is fed by an ancestor or by nothing, and
        // this element is top-level.
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement(
            'root-1',
            'Sw:Provider',
            [],
            [],
            ['content' => [$child]],
            new ContextDefinitions(
                ['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                ['product' => new ContextConsumer(ContextType::Single, required: true)],
            ),
        );

        $rootContext = $this->rootAmbientProductContext();

        static::assertSame($rootContext, $this->resolverWithoutLoaderBacking()->resolve('child-1', [$root], $rootContext));
    }

    #[TestDox('does not back an ancestor provider whose key the ancestor root-scoped consumer lands beside rather than on')]
    public function testAmbientContextDoesNotBackAProviderWhenTheConsumerLandsOnAnotherKey(): void
    {
        // The ancestor does receive the ambient value, but its propertyAlias files it under `item`, while the
        // provider distributes whatever sits under `product`. A root-scoped consumer is therefore not backing
        // by its presence: what it lands on has to be the provider's own key.
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement(
            'root-1',
            'Sw:Provider',
            [],
            [],
            ['content' => [$child]],
            new ContextDefinitions(
                ['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                ['product' => new ContextConsumer(ContextType::Single, required: true, propertyAlias: 'item', scope: ConsumerScope::Root)],
            ),
        );

        $rootContext = $this->rootAmbientProductContext();

        static::assertSame($rootContext, $this->resolverWithoutLoaderBacking()->resolve('child-1', [$root], $rootContext));
    }

    #[TestDox('does not back an ancestor provider from a dotted root-scoped consumer that carries no property alias')]
    public function testDottedRootScopedConsumerWithoutAPropertyAliasBacksNoProvider(): void
    {
        // The property key the consumer writes is compared verbatim, and this is the shape that makes the
        // difference visible. The consumer resolves through the ambient `product` and writes its value under
        // the FULL key `product.manufacturer`, so the provider reading `product` finds nothing at render.
        // Comparing only the base key would call this backed and feed a downstream consumer from nowhere.
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement(
            'root-1',
            'Sw:Provider',
            [],
            [],
            ['content' => [$child]],
            new ContextDefinitions(
                ['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                ['product.manufacturer' => new ContextConsumer(ContextType::Single, required: true, scope: ConsumerScope::Root)],
            ),
        );

        $rootContext = $this->rootAmbientProductContext();

        static::assertSame($rootContext, $this->resolverWithoutLoaderBacking()->resolve('child-1', [$root], $rootContext));
    }

    #[TestDox('backs an ancestor provider from a dotted root-scoped consumer whose property alias is the provider key')]
    public function testDottedRootScopedConsumerAliasedOntoTheProviderKeyBacksIt(): void
    {
        // The same consumer, now aliased onto the provider's key, is the shape that DOES back it: the overlay
        // writes the resolved value under `product`, which is the key the provider distributes from. Dropping
        // the alias from the comparison and matching on the consumer key alone loses this backing.
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement(
            'root-1',
            'Sw:Provider',
            [],
            [],
            ['content' => [$child]],
            new ContextDefinitions(
                ['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                ['product.manufacturer' => new ContextConsumer(ContextType::Single, required: true, propertyAlias: 'product', scope: ConsumerScope::Root)],
            ),
        );

        $available = $this->resolverWithoutLoaderBacking()->resolve('child-1', [$root], $this->rootAmbientProductContext());

        static::assertCount(2, $available);
        static::assertSame('root-1', $available[0]->providerElementId);
        static::assertFalse($available[0]->root);
    }

    #[TestDox('does not back an ancestor provider whose root-scoped consumer names a key the bound source does not supply')]
    public function testAmbientContextDoesNotBackAProviderWhoseConsumerKeyNoAmbientEntrySupplies(): void
    {
        // The consumer writes the provider's own key, so the written-key rule alone would accept it, but it asks
        // for `category` while the bound source supplies `product`. Nothing is delivered to this ancestor at
        // render time and its provider stays unbacked. The FQCNs match either way, which is precisely what the
        // type-only judgment this replaces went on.
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement(
            'root-1',
            'Sw:Provider',
            [],
            [],
            ['content' => [$child]],
            new ContextDefinitions(
                ['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                ['category' => new ContextConsumer(ContextType::Single, required: true, propertyAlias: 'product', scope: ConsumerScope::Root)],
            ),
        );

        $rootContext = $this->rootAmbientProductContext();

        static::assertSame($rootContext, $this->resolverWithoutLoaderBacking()->resolve('child-1', [$root], $rootContext));
    }

    #[TestDox('backs an ancestor provider from the single ambient entry its root-scoped consumer names, beside a second offer of the same type')]
    public function testAmbientBackingSelectsTheConsumedEntryAmongOffersOfOneType(): void
    {
        // Two ambient entries carry the same FQCN and only one is addressed by the ancestor's consumer. Judging
        // the whole ambient set makes the provider's own property ambiguous (two Root candidates, which
        // ElementResolver::pickDefault refuses to choose between) and loses a backing the runtime has: delivery
        // hands this element the `product` entry alone.
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement(
            'root-1',
            'Sw:Provider',
            [],
            [],
            ['content' => [$child]],
            new ContextDefinitions(
                ['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                ['product' => new ContextConsumer(ContextType::Single, required: true, scope: ConsumerScope::Root)],
            ),
        );

        $rootContext = [...$this->rootAmbientProductContext(), new ProvidedContext(
            contextKey: 'featuredProduct',
            fqcn: SalesChannelProductEntity::class,
            contextType: ContextType::Single,
            providerElementId: null,
            distribution: DistributionStrategy::Broadcast,
            root: true,
        )];

        $available = $this->resolverWithoutLoaderBacking()->resolve('child-1', [$root], $rootContext);

        static::assertCount(3, $available);
        static::assertSame('root-1', $available[0]->providerElementId);
        static::assertSame('product', $available[0]->contextKey);
        static::assertFalse($available[0]->root);
    }

    #[TestDox('returns an empty set for an unknown element id')]
    public function testUnknownElementYieldsEmpty(): void
    {
        // Non-empty root-ambient context so the located-element return (which appends it at every depth)
        // cannot produce the same [] as the not-found return under test.
        $root = new StoredElement('root-1', 'Sw:Block');

        static::assertSame([], $this->resolver()->resolve('missing', [$root], $this->rootAmbientProductContext()));
    }

    #[TestDox('rejects a top-level target whose own provider set collides on a child-facing key')]
    public function testRejectsCollidingChildFacingKeysOnTheTarget(): void
    {
        // Collision axis: distinct provider map keys whose broadcast configs both rename the matched child
        // key to 'item'. The check must run on the target itself: a top-level element has no ancestors, so
        // the per-ancestor pass never reaches it.
        $root = new StoredElement(
            'root-1',
            'Sw:Block',
            [],
            [],
            [],
            $this->collidingProviderDefinitions(),
        );

        try {
            $this->resolver()->resolve('root-1', [$root], []);
            static::fail('Expected a provider delivery collision.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::PROVIDER_DELIVERY_COLLISION, $exception->getErrorCode());
        }
    }

    #[TestDox('rejects a nested target whose ancestor provider set collides on a child-facing key, even when the target itself is clean')]
    public function testRejectsCollidingAncestorChildFacingKeys(): void
    {
        // Collision axis: the ANCESTOR's two provider map keys both rename the matched child key to 'item'.
        // The target's own provider set is clean, so only the per-ancestor validateProviderDeliveryKeys
        // call can throw — dropping it while keeping the target call silently accepts the colliding layout.
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement(
            'root-1',
            'Sw:Block',
            [],
            [],
            ['content' => [$child]],
            $this->collidingProviderDefinitions(),
        );

        try {
            $this->resolver()->resolve('child-1', [$root], []);
            static::fail('Expected a provider delivery collision.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::PROVIDER_DELIVERY_COLLISION, $exception->getErrorCode());
        }
    }

    /**
     * The shape the root-source registry mints: marked root-ambient and carrying no provider element id.
     *
     * @return list<ProvidedContext>
     */
    private function rootAmbientProductContext(): array
    {
        return [new ProvidedContext(
            contextKey: 'product',
            fqcn: SalesChannelProductEntity::class,
            contextType: ContextType::Single,
            providerElementId: null,
            distribution: DistributionStrategy::Broadcast,
            root: true,
        )];
    }

    /**
     * A three-level tree whose chain source is element-provided: root-1 declares a backed `product` provider,
     * level-2 carries the given consumers, and deep-1 sits below it. What reaches deep-1 therefore says whether
     * the chain relayed it, with no root-ambient set in play to answer for it.
     *
     * @param array<string, ContextConsumer> $intermediateConsumers
     *
     * @return list<StoredElement>
     */
    private function providerChain(array $intermediateConsumers): array
    {
        $deep = new StoredElement('deep-1', 'Sw:Block');
        $level2 = new StoredElement(
            'level-2',
            'Sw:Block',
            [],
            [],
            ['content' => [$deep]],
            new ContextDefinitions([], $intermediateConsumers),
        );
        $root = new StoredElement(
            'root-1',
            'Sw:Provider',
            [],
            [],
            ['content' => [$level2]],
            new ContextDefinitions(
                ['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                [],
            ),
        );

        return [$root];
    }

    private function collidingProviderDefinitions(): ContextDefinitions
    {
        return new ContextDefinitions(
            [
                'product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::aliased('item')),
                'category' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::aliased('item')),
            ],
            [],
        );
    }

    private function resolver(): AvailableContextResolver
    {
        // A single complete loader backs the provider's own `product` property, so its declared provider
        // resolves on its element (Level 2) and is exposed to descendants.
        return $this->resolverWithLoaderMap(new ContentSystemDataLoaderMap(
            ['product_loader' => [new LoaderTypeCapability(SalesChannelProductEntity::class)]],
            ['product_loader' => new LoaderConfigSpecification([])],
        ));
    }

    /**
     * No loader can produce the provider's declared FQCN, so the only thing that can back a declared provider
     * is context available at its own position.
     */
    private function resolverWithoutLoaderBacking(): AvailableContextResolver
    {
        return $this->resolverWithLoaderMap(new ContentSystemDataLoaderMap([], []));
    }

    private function resolverWithLoaderMap(ContentSystemDataLoaderMap $map): AvailableContextResolver
    {
        $providerSpec = new ContentSystemElementTypeSpecification(
            'Sw:Provider',
            'Provider',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            ['product' => new PropertySpecification(
                'product',
                new PropertyType(SalesChannelProductEntity::class, false, null, null),
                false,
                '',
                '',
                null,
            )],
            [],
        );

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => $name === 'Sw:Provider');
        $registry->method('get')->willReturn($providerSpec);

        $typeResolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $typeResolver->method('resolve')->willReturn($map);

        $configSerializers = static::createStub(DataLoaderConfigSerializerProvider::class);
        $configSerializers->method('decode')->willReturn(static::createStub(AbstractContentDataLoaderConfig::class));

        $elementResolver = new ElementResolver($registry, $typeResolver, $configSerializers, static::createStub(DataLoaderProvider::class));

        return new AvailableContextResolver($registry, $elementResolver, new ProviderDeliveryKeyResolver(), new ContextPathResolver());
    }
}
