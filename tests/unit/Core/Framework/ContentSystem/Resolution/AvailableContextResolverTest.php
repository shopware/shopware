<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
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
    #[TestDox('returns the bound source root-ambient context for a top-level element, or empty when the source exposes none (header/footer)')]
    public function testTopLevelReceivesRootAmbient(): void
    {
        $root = new StoredElement('root-1', 'Sw:Block');

        $rootContext = $this->rootAmbientProductContext();

        static::assertSame($rootContext, $this->resolver()->resolve('root-1', [$root], $rootContext));
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

    #[TestDox('excludes a top-level sibling root-ambient context from a nested element')]
    public function testNestedDoesNotReceiveRootAmbient(): void
    {
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement('root-1', 'Sw:Block', [], [], ['content' => [$child]]);

        $rootContext = $this->rootAmbientProductContext();

        static::assertSame([], $this->resolver()->resolve('child-1', [$root], $rootContext));
    }

    #[TestDox('exposes a backed ancestor provider to its direct child but not past a non-redistributing intermediate')]
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

        $resolver = $this->resolver();

        static::assertSame(['product'], $this->keys($resolver->resolve('child-1', [$root], [])));
        static::assertSame([], $resolver->resolve('grandchild-1', [$root], []));
    }

    #[TestDox('re-exposes incoming root-ambient context through a redistributing intermediate with the inflowing type')]
    public function testRedistributeReExposesIncomingRootAmbient(): void
    {
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement(
            'root-1',
            'Sw:Block',
            [],
            [],
            ['content' => [$child]],
            new ContextDefinitions(
                [],
                ['product' => new ContextConsumer(ContextType::Single, required: false, redistribute: true)],
            ),
        );

        $available = $this->resolver()->resolve('child-1', [$root], $this->rootAmbientProductContext());

        static::assertCount(1, $available);
        static::assertSame('product', $available[0]->contextKey);
        static::assertSame(SalesChannelProductEntity::class, $available[0]->fqcn);
        static::assertSame(ContextType::Single, $available[0]->contextType);
        static::assertSame('root-1', $available[0]->providerElementId);
        static::assertSame(DistributionStrategy::Broadcast, $available[0]->distribution);
    }

    #[TestDox('remaps the re-exposed key to the consumer alias while keeping the inflowing type')]
    public function testRedistributeConsumerAliasRemapsExposedKey(): void
    {
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement(
            'root-1',
            'Sw:Block',
            [],
            [],
            ['content' => [$child]],
            new ContextDefinitions(
                [],
                ['product' => new ContextConsumer(ContextType::Single, required: false, redistribute: true, consumerAlias: 'item')],
            ),
        );

        $available = $this->resolver()->resolve('child-1', [$root], $this->rootAmbientProductContext());

        static::assertCount(1, $available);
        static::assertSame('item', $available[0]->contextKey);
        static::assertSame(SalesChannelProductEntity::class, $available[0]->fqcn);
    }

    #[TestDox('does not re-expose a redistribute consumer whose key is absent from the incoming context')]
    public function testRedistributeWithoutMatchingIncomingKeyExposesNothing(): void
    {
        // F1 regression guard: a redistribute consumer re-exposes only a key that actually flows into the
        // element, so unconditional re-exposure cannot re-open the over-permissive availability leak.
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement(
            'root-1',
            'Sw:Block',
            [],
            [],
            ['content' => [$child]],
            new ContextDefinitions(
                [],
                ['category' => new ContextConsumer(ContextType::Single, required: false, redistribute: true)],
            ),
        );

        static::assertSame([], $this->resolver()->resolve('child-1', [$root], $this->rootAmbientProductContext()));
    }

    #[TestDox('does not re-expose incoming context through a consumer that does not redistribute')]
    public function testNonRedistributingConsumerDoesNotReExposeIncomingContext(): void
    {
        $child = new StoredElement('child-1', 'Sw:Block');
        $root = new StoredElement(
            'root-1',
            'Sw:Block',
            [],
            [],
            ['content' => [$child]],
            new ContextDefinitions(
                [],
                ['product' => new ContextConsumer(ContextType::Single, required: false, redistribute: false)],
            ),
        );

        static::assertSame([], $this->resolver()->resolve('child-1', [$root], $this->rootAmbientProductContext()));
    }

    #[TestDox('accumulates redistributed context across multiple intermediates down to a deep descendant')]
    public function testRedistributeChainsAcrossMultipleLevels(): void
    {
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

        $available = $this->resolver()->resolve('deep-1', [$root], $this->rootAmbientProductContext());

        static::assertCount(1, $available);
        static::assertSame('product', $available[0]->contextKey);
        static::assertSame('level-2', $available[0]->providerElementId);
    }

    #[TestDox('returns an empty set for an unknown element id')]
    public function testUnknownElementYieldsEmpty(): void
    {
        $root = new StoredElement('root-1', 'Sw:Block');

        static::assertSame([], $this->resolver()->resolve('missing', [$root], []));
    }

    #[TestDox('rejects a top-level target whose own provider set collides on a child-facing key')]
    public function testRejectsCollidingChildFacingKeysOnTheTarget(): void
    {
        // Collision axis: distinct provider map keys whose broadcast configs both rename the matched child
        // key to 'item'. The check must run on the target itself, past the top-level early return that
        // otherwise skips every element of a top-level path.
        $root = new StoredElement(
            'root-1',
            'Sw:Block',
            [],
            [],
            [],
            new ContextDefinitions(
                [
                    'product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::aliased('item')),
                    'category' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::aliased('item')),
                ],
                [],
            ),
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
            new ContextDefinitions(
                [
                    'product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::aliased('item')),
                    'category' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::aliased('item')),
                ],
                [],
            ),
        );

        try {
            $this->resolver()->resolve('child-1', [$root], []);
            static::fail('Expected a provider delivery collision.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::PROVIDER_DELIVERY_COLLISION, $exception->getErrorCode());
        }
    }

    /**
     * @param list<ProvidedContext> $available
     *
     * @return list<string>
     */
    private function keys(array $available): array
    {
        return array_map(static fn (ProvidedContext $provided): string => $provided->contextKey, $available);
    }

    /**
     * @return list<ProvidedContext>
     */
    private function rootAmbientProductContext(): array
    {
        return [new ProvidedContext(
            contextKey: 'product',
            fqcn: SalesChannelProductEntity::class,
            contextType: ContextType::Single,
            providerElementId: VirtualRootWrapper::VIRTUAL_ROOT_ID,
            distribution: DistributionStrategy::Broadcast,
        )];
    }

    private function resolver(): AvailableContextResolver
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

        // A single complete loader backs the provider's own `product` property, so its declared provider
        // resolves on its element (Level 2) and is exposed to descendants.
        $typeResolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $typeResolver->method('resolve')->willReturn(new ContentSystemDataLoaderMap(
            ['product_loader' => [new LoaderTypeCapability(SalesChannelProductEntity::class)]],
            ['product_loader' => new LoaderConfigSpecification([])],
        ));

        $configSerializers = static::createStub(DataLoaderConfigSerializerProvider::class);
        $configSerializers->method('decode')->willReturn(static::createStub(AbstractContentDataLoaderConfig::class));

        $elementResolver = new ElementResolver($registry, $typeResolver, $configSerializers, static::createStub(DataLoaderProvider::class));

        return new AvailableContextResolver($registry, $elementResolver);
    }
}
