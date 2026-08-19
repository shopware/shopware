<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataContext;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextDeliveryResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextDistributor;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\DataContextResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElementLowering;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IteratorDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\SlicedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubContextStruct;

/**
 * Runs the live context path and the rebuilt one over the same stored tree and asserts they deliver the same
 * thing to the same element. It is the evidence that the rebuild is a reimplementation rather than a
 * reinterpretation, and it is only writable while both implementations exist — at cutover
 * `ContentElementLowering` and `DataContextResolver` go, and this comparison cannot be reconstructed
 * afterwards from either side alone.
 *
 * The two sides do not report the same thing natively, so the comparison normalises rather than assumes:
 * the live path MERGES delivered context into each element's property map, while the rebuilt path returns
 * the delivered context on its own. The live side is therefore diffed against a snapshot taken before
 * distribution runs, and what that diff yields is compared against the index.
 *
 * WHAT THIS HARNESS CANNOT SEE. A green run here means the two paths agreed on what was compared, which is
 * less than "the two paths agree". Three divergences are masked by the comparison method rather than by
 * either implementation, and a reader trusting a green run needs them stated:
 *
 * 1. A delivered value identical to the value already sitting under that key is invisible to the snapshot
 *    diff. Every fixture keeps delivered values distinct from stored ones; the method cannot do better
 *    without an instrumented live path.
 * 2. This harness seeds loader values with `setProperty()` unconditionally, while `ContentElementHydrator`
 *    writes only `if ($result->hasData())`. That makes the live side behave like the rebuilt one on exactly
 *    the divergence the two implementations have — a loader that found nothing overwrites the stored value
 *    here and does not overwrite it in production. `ContextDeliveryResolverTest` pins the rebuilt behaviour
 *    directly, because this file structurally cannot show it.
 * 3. `ContentElement` keeps struct and non-struct property values in two stores, and `getProperties()`
 *    returns `array_merge($structProperties, $nonStructProperties)` — so a non-struct value WINS a key
 *    collision. A child holding a scalar under a key and then receiving a Struct under that same key reads
 *    back the scalar, and the diff sees no change at all. No fixture here pairs a stored scalar with a
 *    delivered Struct under one key, and one would compare nothing if it did.
 *
 * Cases where the two implementations legitimately disagree are kept OUT of this file rather than reconciled
 * into it — a keyed `keyProperty` arriving as delivered context is one, and it is pinned in
 * `ContextDeliveryResolverTest` instead.
 *
 * `CoversNothing` is deliberate: this file owns no class's coverage. It asserts an equivalence between two
 * implementations that each have their own dedicated tests, and attributing their coverage here would
 * report the equivalence check as though it were the contract test for either side.
 *
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class ContextDistributionDifferentialTest extends TestCase
{
    /**
     * Every fixture pairs a stored forest with the loader values that forest's elements resolved. Struct
     * values arrive through the loader map rather than through stored properties because a stored value is
     * wrapped by `StoredValue::fromDecoded()`, which takes scalars, nulls and arrays only — so the dotted
     * path cases exercise the loader tier by construction.
     *
     * @return iterable<string, array{list<StoredElement>, array<string, array<string, mixed>>}>
     */
    public static function equivalentForestProvider(): iterable
    {
        yield 'broadcast to two consumers' => [
            [self::parentOf('items', BroadcastDistributionConfig::simple(), ['left-item', 'right-item'], [
                self::consumer('child-1', 'items'),
                self::consumer('child-2', 'items'),
            ])],
            [],
        ];

        yield 'broadcast where one child consumes nothing' => [
            [self::parentOf('items', BroadcastDistributionConfig::simple(), ['only'], [
                self::consumer('child-1', 'items'),
                StoredElementBuilder::create('Sw:Text', 'child-2')->build(),
            ])],
            [],
        ];

        foreach (self::strategies() as $label => $config) {
            yield $label . ', too few items' => [
                [self::parentOf('items', $config, ['first-item'], self::keyedConsumerPair())],
                [],
            ];
            yield $label . ', data is not an array' => [
                [self::parentOf('items', $config, 'not-an-array', self::keyedConsumerPair())],
                [],
            ];
        }

        yield 'keyed, one key hits and one misses' => [
            [self::parentOf('items', KeyedDistributionConfig::simple(), ['present' => 'present-item'], self::keyedConsumerPair())],
            [],
        ];

        yield 'consumer alias selects children under another name' => [
            [self::parentOf('featuredProduct', BroadcastDistributionConfig::aliased('product'), 'product-data', [
                self::consumer('child-1', 'product'),
            ])],
            [],
        ];

        yield 'property alias renames the delivered key' => [
            [self::parentOf('product', BroadcastDistributionConfig::simple(), 'product-data', [
                StoredElementBuilder::create('Sw:Box', 'child-1')
                    ->withConsumer('product', ContextType::Single, propertyAlias: 'myProduct')
                    ->build(),
            ])],
            [],
        ];

        yield 'both aliases at once' => [
            [self::parentOf('featuredProduct', BroadcastDistributionConfig::aliased('product'), 'product-data', [
                StoredElementBuilder::create('Sw:Box', 'child-1')
                    ->withConsumer('product', ContextType::Single, propertyAlias: 'myProduct')
                    ->build(),
            ])],
            [],
        ];

        yield 'null provider value delivers nothing' => [
            [self::parentOf('product', BroadcastDistributionConfig::simple(), null, [
                self::consumer('child-1', 'product'),
            ])],
            [],
        ];

        yield 'dotted consumer path resolves through a loaded struct' => [
            [self::parentOf('product', BroadcastDistributionConfig::simple(), null, [
                self::consumer('child-1', 'product.cover'),
            ])],
            ['parent-1' => ['product' => new StubContextStruct('cover-url')]],
        ];

        yield 'optional dotted path against a non-struct yields null' => [
            [self::parentOf('product', BroadcastDistributionConfig::simple(), 'not-a-struct', [
                self::consumer('child-1', 'product.cover'),
            ])],
            [],
        ];

        yield 'one child fills every matching key from its one indexed position' => [
            [self::parentOf('product', IndexedDistributionConfig::simple(), null, [
                StoredElementBuilder::create('Sw:Box', 'child-1')
                    ->withConsumer('product', ContextType::Single)
                    ->withConsumer('product.cover', ContextType::Single)
                    ->build(),
            ])],
            ['parent-1' => ['product' => [new StubContextStruct('first-cover'), new StubContextStruct('second-cover')]]],
        ];

        yield 'two providers collide on one consumer key' => [
            [StoredElementBuilder::create('Sw:Section', 'parent-1')
                ->withProperty('firstSource', 'first-data')
                ->withProperty('secondSource', 'second-data')
                ->withProvider('firstSource', BroadcastDistributionConfig::aliased('product'))
                ->withProvider('secondSource', BroadcastDistributionConfig::aliased('product'))
                ->withSlot('main', [self::consumer('child-1', 'product')])
                ->build()],
            [],
        ];

        yield 'children pool across two slots' => [
            [StoredElementBuilder::create('Sw:Section', 'parent-1')
                ->withProperty('items', ['first-item', 'second-item', 'third-item'])
                ->withProvider('items', IndexedDistributionConfig::simple())
                ->withSlot('left', [self::consumer('child-1', 'items'), self::consumer('child-2', 'items')])
                ->withSlot('right', [self::consumer('child-3', 'items')])
                ->build()],
            [],
        ];

        yield 'chained redistribution, two hops' => [
            [self::chainedForest()],
            [],
        ];

        yield 'chained redistribution, three hops' => [
            [self::deeplyChainedForest()],
            [],
        ];

        yield 'two roots each distributing their own value' => [
            [
                self::parentOf('product', BroadcastDistributionConfig::simple(), 'first-data', [
                    self::consumer('child-1', 'product'),
                ]),
                StoredElementBuilder::create('Sw:Section', 'parent-2')
                    ->withProperty('product', 'second-data')
                    ->withProvider('product', BroadcastDistributionConfig::simple())
                    ->withSlot('main', [self::consumer('child-2', 'product')])
                    ->build(),
            ],
            [],
        ];
    }

    /**
     * @param list<StoredElement> $forest
     * @param array<string, array<string, mixed>> $loaderValues
     */
    #[TestDox('delivers the same context per element as the live path')]
    #[DataProvider('equivalentForestProvider')]
    public function testBothPathsDeliverTheSameContext(array $forest, array $loaderValues): void
    {
        $live = $this->livePathDeliveries($forest, $loaderValues);
        $rebuilt = $this->rebuiltPathDeliveries($forest, $loaderValues);

        static::assertSame($live, $rebuilt);
    }

    #[TestDox('fails the same way as the live path when a required consumer path cannot be resolved')]
    public function testBothPathsThrowTheSameFailureForARequiredConsumer(): void
    {
        $forest = [self::parentOf('product', BroadcastDistributionConfig::simple(), 'not-a-struct', [
            StoredElementBuilder::create('Sw:Box', 'child-1')
                ->withConsumer('product.cover', ContextType::Single, required: true)
                ->build(),
        ])];
        $expected = ContentSystemException::contextPathNotResolvable(
            'product.cover',
            'child-1',
            'Context data is not a Struct instance'
        );

        $liveFailure = $this->captureFailure(fn () => $this->livePathDeliveries($forest, []));
        $rebuiltFailure = $this->captureFailure(fn () => $this->rebuiltPathDeliveries($forest, []));

        static::assertEquals($expected, $liveFailure);
        static::assertEquals($expected, $rebuiltFailure);
    }

    /**
     * @return array<string, DistributionConfig>
     */
    private static function strategies(): array
    {
        return [
            'indexed' => IndexedDistributionConfig::simple(),
            'keyed' => KeyedDistributionConfig::simple(),
            'sliced' => SlicedDistributionConfig::withSliceSize(1),
            'iterator' => IteratorDistributionConfig::simple(),
        ];
    }

    /**
     * @return list<StoredElement>
     */
    private static function keyedConsumerPair(): array
    {
        return [
            StoredElementBuilder::create('Sw:Box', 'child-1')
                ->withProperty('data_key', 'present')
                ->withConsumer('items', ContextType::Single)
                ->build(),
            StoredElementBuilder::create('Sw:Box', 'child-2')
                ->withProperty('data_key', 'absent')
                ->withConsumer('items', ContextType::Single)
                ->build(),
        ];
    }

    private static function consumer(string $id, string $contextKey): StoredElement
    {
        return StoredElementBuilder::create('Sw:Box', $id)
            ->withConsumer($contextKey, ContextType::Single)
            ->build();
    }

    /**
     * @param list<StoredElement> $children
     */
    private static function parentOf(
        string $contextKey,
        DistributionConfig $config,
        mixed $value,
        array $children,
    ): StoredElement {
        $builder = StoredElementBuilder::create('Sw:Section', 'parent-1')
            ->withProvider($contextKey, $config)
            ->withSlot('main', $children);

        if ($value !== null) {
            $builder->withProperty($contextKey, $value);
        }

        return $builder->build();
    }

    private static function chainedForest(): StoredElement
    {
        $middle = StoredElementBuilder::create('Sw:Section', 'child-1')
            ->withConsumer('product', ContextType::Single)
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [self::consumer('grandchild-1', 'product')])
            ->build();

        return StoredElementBuilder::create('Sw:Section', 'parent-1')
            ->withProperty('product', 'product-data')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$middle])
            ->build();
    }

    private static function deeplyChainedForest(): StoredElement
    {
        $deepest = StoredElementBuilder::create('Sw:Box', 'great-grandchild-1')
            ->withConsumer('product', ContextType::Single, propertyAlias: 'finalProduct')
            ->build();
        $inner = StoredElementBuilder::create('Sw:Section', 'grandchild-1')
            ->withConsumer('product', ContextType::Single)
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$deepest])
            ->build();
        $middle = StoredElementBuilder::create('Sw:Section', 'child-1')
            ->withConsumer('product', ContextType::Single)
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$inner])
            ->build();

        return StoredElementBuilder::create('Sw:Section', 'parent-1')
            ->withProperty('product', 'product-data')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('main', [$middle])
            ->build();
    }

    /**
     * Lower, seed the loader values the hydrator would have written, snapshot, resolve, then diff. The diff
     * is what isolates delivered context from the stored values it was merged into.
     *
     * @param list<StoredElement> $forest
     * @param array<string, array<string, mixed>> $loaderValues
     *
     * @return array<string, array<string, mixed>>
     */
    private function livePathDeliveries(array $forest, array $loaderValues): array
    {
        $lowered = (new ContentElementLowering())->lowerTree($forest);

        $byId = [];
        foreach ($lowered as $root) {
            $this->collectLowered($root, $byId);
        }

        $before = [];
        foreach ($byId as $id => $element) {
            foreach ($loaderValues[$id] ?? [] as $key => $value) {
                $element->setProperty($key, $value);
            }
            $before[$id] = $element->getProperties();
        }

        $resolver = new DataContextResolver(new ContextPathResolver());
        foreach ($lowered as $root) {
            $resolver->resolve($root);
        }

        $delivered = [];
        foreach ($byId as $id => $element) {
            $delivered[$id] = $this->addedOrChanged($before[$id], $element->getProperties());
        }

        return $this->normalise($delivered);
    }

    /**
     * @param list<StoredElement> $forest
     * @param array<string, array<string, mixed>> $loaderValues
     *
     * @return array<string, array<string, mixed>>
     */
    private function rebuiltPathDeliveries(array $forest, array $loaderValues): array
    {
        $resolver = new ContextDeliveryResolver(new ContextDistributor(new ContextPathResolver()));

        $delivered = [];
        foreach ($resolver->resolve($forest, $loaderValues)->all() as $id => $delivery) {
            $delivered[$id] = $delivery->context;
        }

        return $this->normalise($delivered);
    }

    /**
     * @param array<string, ContentElement> $byId
     */
    private function collectLowered(ContentElement $element, array &$byId): void
    {
        $byId[$element->getId()] = $element;

        foreach ($element->allSlotElements() as $child) {
            $this->collectLowered($child, $byId);
        }
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     *
     * @return array<string, mixed>
     */
    private function addedOrChanged(array $before, array $after): array
    {
        $changed = [];

        foreach ($after as $key => $value) {
            if (!\array_key_exists($key, $before) || $before[$key] !== $value) {
                $changed[$key] = $value;
            }
        }

        return $changed;
    }

    /**
     * Key order carries no meaning on either side — the live path's order comes from the property map it
     * merged into, the rebuilt path's from the order it wrote deliveries. Sorting both makes the comparison
     * about content while keeping `assertSame`, so struct values still compare by identity.
     *
     * @param array<string, array<string, mixed>> $delivered
     *
     * @return array<string, array<string, mixed>>
     */
    private function normalise(array $delivered): array
    {
        ksort($delivered);

        foreach ($delivered as $id => $context) {
            ksort($context);
            $delivered[$id] = $context;
        }

        return $delivered;
    }

    private function captureFailure(callable $run): ?ContentSystemException
    {
        try {
            $run();
        } catch (ContentSystemException $exception) {
            return $exception;
        }

        return null;
    }
}
