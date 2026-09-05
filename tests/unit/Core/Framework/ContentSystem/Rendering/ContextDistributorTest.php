<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IteratorDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\SlicedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDistributor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubContextStruct;

/**
 * Every expectation here asserts the whole delivered map with `assertSame` rather than checking presence and
 * value separately: one expression then states presence, absence and value together, and the difference
 * between a delivered null and a key that was never delivered is the distinction the whole module turns on.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextDistributor::class)]
class ContextDistributorTest extends TestCase
{
    /**
     * Each strategy's two under-supply shapes: too few items for the consumers, and provider data that is
     * not a list at all. They differ per strategy and the differences are the contract — indexed and keyed
     * mint explicit nulls, sliced mints empty arrays, iterator writes no key whatsoever.
     *
     * @return iterable<string, array{DistributionConfig, mixed, array<string, mixed>, array<string, mixed>}>
     */
    public static function underSuppliedStrategyProvider(): iterable
    {
        yield 'indexed, too few items' => [
            IndexedDistributionConfig::simple(),
            ['first-item'],
            ['items' => 'first-item'],
            ['items' => null],
        ];
        yield 'indexed, data is not an array' => [
            IndexedDistributionConfig::simple(),
            'not-an-array',
            ['items' => null],
            ['items' => null],
        ];
        yield 'keyed, no entry for the key' => [
            KeyedDistributionConfig::simple(),
            ['present' => 'present-item'],
            ['items' => 'present-item'],
            ['items' => null],
        ];
        yield 'keyed, data is not an array' => [
            KeyedDistributionConfig::simple(),
            'not-an-array',
            ['items' => null],
            ['items' => null],
        ];
        yield 'sliced, no slice left' => [
            SlicedDistributionConfig::withSliceSize(1),
            ['first-item'],
            ['items' => ['first-item']],
            ['items' => []],
        ];
        yield 'sliced, data is not an array' => [
            SlicedDistributionConfig::withSliceSize(1),
            'not-an-array',
            ['items' => []],
            ['items' => []],
        ];
        yield 'iterator, too few items' => [
            IteratorDistributionConfig::simple(),
            ['first-item'],
            ['items' => 'first-item'],
            [],
        ];
        yield 'iterator, data is not an array' => [
            IteratorDistributionConfig::simple(),
            'not-an-array',
            [],
            [],
        ];
    }

    #[TestDox('delivers a broadcast value to every consuming child')]
    public function testBroadcastReachesEveryConsumingChild(): void
    {
        $children = [$this->consumerOf('child-1', 'product'), $this->consumerOf('child-2', 'product')];
        $parent = $this->providerOf('product', BroadcastDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute($parent, ['product' => 'product-data'], $children);

        static::assertSame(['product' => 'product-data'], $deliveries[0]->context);
        static::assertSame(['product' => 'product-data'], $deliveries[1]->context);
    }

    #[TestDox('delivers nothing to a child that consumes no matching key')]
    public function testNonConsumingChildReceivesNothing(): void
    {
        $children = [StoredElementBuilder::create('Sw:Text', 'child-1')->build()];
        $parent = $this->providerOf('product', BroadcastDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute($parent, ['product' => 'product-data'], $children);

        static::assertTrue($deliveries[0]->isEmpty());
    }

    #[TestDox('returns one delivery per child, carrying that child id, even when it received nothing')]
    public function testReturnsOneDeliveryPerChildAlignedWithTheInput(): void
    {
        $children = [
            StoredElementBuilder::create('Sw:Text', 'child-1')->build(),
            $this->consumerOf('child-2', 'product'),
        ];
        $parent = $this->providerOf('product', BroadcastDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute($parent, ['product' => 'product-data'], $children);

        static::assertCount(2, $deliveries);
        static::assertSame('child-1', $deliveries[0]->elementId);
        static::assertSame('child-2', $deliveries[1]->elementId);
    }

    #[TestDox('delivers under the property alias when the consumer declares one')]
    public function testPropertyAliasIsTheDeliveredKey(): void
    {
        $child = StoredElementBuilder::create('Sw:Box', 'child-1')
            ->withConsumer('product', ContextType::Single, propertyAlias: 'myProduct')
            ->build();
        $parent = $this->providerOf('product', BroadcastDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute($parent, ['product' => 'product-data'], [$child]);

        static::assertSame(['myProduct' => 'product-data'], $deliveries[0]->context);
    }

    #[TestDox('reads the value under the provider key while the consumer alias selects the children')]
    public function testConsumerAliasSelectsChildrenIndependentlyOfTheProviderKey(): void
    {
        $children = [$this->consumerOf('child-1', 'product')];
        $parent = $this->providerOf('featuredProduct', BroadcastDistributionConfig::aliased('product'));

        $deliveries = $this->distributor()->distribute($parent, ['featuredProduct' => 'product-data'], $children);

        static::assertSame(['product' => 'product-data'], $deliveries[0]->context);
    }

    #[TestDox('resolves a dotted consumer key through the delivered struct')]
    public function testDottedConsumerKeyResolvesThroughTheStruct(): void
    {
        $children = [$this->consumerOf('child-1', 'product.cover')];
        $parent = $this->providerOf('product', BroadcastDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute(
            $parent,
            ['product' => new StubContextStruct('cover-url')],
            $children
        );

        static::assertSame(['product.cover' => 'cover-url'], $deliveries[0]->context);
    }

    #[TestDox('leaves a consumer key no provider matched out of the delivery entirely')]
    public function testUnmatchedConsumerKeyIsAbsentFromTheDelivery(): void
    {
        $child = StoredElementBuilder::create('Sw:Box', 'child-1')
            ->withConsumer('product', ContextType::Single)
            ->withConsumer('category', ContextType::Single)
            ->build();
        $parent = $this->providerOf('product', BroadcastDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute($parent, ['product' => 'product-data'], [$child]);

        static::assertSame(['product' => 'product-data'], $deliveries[0]->context);
    }

    #[TestDox('lets the last provider win when two providers deliver under the same consumer key')]
    public function testLastProviderWinsOnACollision(): void
    {
        $children = [$this->consumerOf('child-1', 'product')];
        $parent = StoredElementBuilder::create('Sw:Section', 'parent-1')
            ->withProvider('firstSource', BroadcastDistributionConfig::aliased('product'))
            ->withProvider('secondSource', BroadcastDistributionConfig::aliased('product'))
            ->build();

        $deliveries = $this->distributor()->distribute(
            $parent,
            ['firstSource' => 'first-data', 'secondSource' => 'second-data'],
            $children
        );

        static::assertSame(['product' => 'second-data'], $deliveries[0]->context);
    }

    /**
     * Indexed distribution with two distinct items is what makes this observable. A child occupies ONE
     * position however many of its keys match, so both keys fill from the FIRST item. Matching per key
     * instead of per child would hand this child position 0 and then position 1, and the second item would
     * overwrite both keys — which a broadcast strategy could never show, since it hands every position the
     * same value and the resulting map is identical either way.
     */
    #[TestDox('fills every matching consumer key on one child from the single item its one position took')]
    public function testOneValueFillsEveryMatchingKeyOnTheSameChild(): void
    {
        $first = new StubContextStruct('first-cover');
        $second = new StubContextStruct('second-cover');
        $child = StoredElementBuilder::create('Sw:Box', 'child-1')
            ->withConsumer('product', ContextType::Single)
            ->withConsumer('product.cover', ContextType::Single)
            ->build();
        $parent = $this->providerOf('product', IndexedDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute($parent, ['product' => [$first, $second]], [$child]);

        static::assertSame(['product' => $first, 'product.cover' => 'first-cover'], $deliveries[0]->context);
    }

    #[TestDox('hands out indexed positions in the order the children were given')]
    public function testIndexedPositionsFollowTheGivenChildOrder(): void
    {
        $children = [
            $this->consumerOf('child-1', 'items'),
            $this->consumerOf('child-2', 'items'),
            $this->consumerOf('child-3', 'items'),
        ];
        $parent = $this->providerOf('items', IndexedDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute(
            $parent,
            ['items' => ['first-item', 'second-item', 'third-item']],
            $children
        );

        static::assertSame(['items' => 'first-item'], $deliveries[0]->context);
        static::assertSame(['items' => 'second-item'], $deliveries[1]->context);
        static::assertSame(['items' => 'third-item'], $deliveries[2]->context);
    }

    #[TestDox('gives a dotted consumer its own position in an indexed distribution')]
    public function testDottedConsumerOccupiesItsOwnIndexedPosition(): void
    {
        $first = new StubContextStruct('first-cover');
        $second = new StubContextStruct('second-cover');
        $children = [$this->consumerOf('child-1', 'product'), $this->consumerOf('child-2', 'product.cover')];
        $parent = $this->providerOf('product', IndexedDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute($parent, ['product' => [$first, $second]], $children);

        static::assertSame(['product' => $first], $deliveries[0]->context);
        static::assertSame(['product.cover' => 'second-cover'], $deliveries[1]->context);
    }

    #[TestDox('skips a child that consumes nothing when counting indexed positions')]
    public function testANonConsumingChildTakesNoIndexedPosition(): void
    {
        $children = [
            StoredElementBuilder::create('Sw:Text', 'child-1')->build(),
            $this->consumerOf('child-2', 'items'),
        ];
        $parent = $this->providerOf('items', IndexedDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute($parent, ['items' => ['first-item']], $children);

        static::assertSame([], $deliveries[0]->context);
        static::assertSame(['items' => 'first-item'], $deliveries[1]->context);
    }

    /**
     * A root-scoped consumer is filled from the layout's root-ambient map, never off the parent chain, so it
     * is invisible to this class. For an indexed strategy that means it takes no POSITION: without the skip
     * the two children below would be positions zero and one, and the parent-scoped one would receive the
     * SECOND item rather than the first — an off-by-one nothing about the delivered key set would show.
     */
    #[TestDox('gives a root-scoped consumer no position in an indexed distribution')]
    public function testRootScopedConsumerTakesNoIndexedPosition(): void
    {
        $children = [
            $this->rootScopedConsumerOf('child-1', 'items'),
            $this->consumerOf('child-2', 'items'),
        ];
        $parent = $this->providerOf('items', IndexedDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute($parent, ['items' => ['first-item', 'second-item']], $children);

        static::assertSame([], $deliveries[0]->context);
        static::assertSame(['items' => 'first-item'], $deliveries[1]->context);
    }

    /**
     * The second scope skip, the one inside the per-key write. This child matches the provider key through
     * its PARENT-scoped consumer, so it does take a position and the write loop does run over its consumer
     * map — and the root-scoped key in that same map must still be left alone. A fixture whose only consumer
     * were root-scoped could not discriminate: it never reaches the write loop at all.
     */
    #[TestDox('writes no key for a root-scoped consumer of a child that matched through a parent-scope consumer')]
    public function testRootScopedConsumerKeyIsSkippedOnAChildThatMatchedThroughParentScope(): void
    {
        $child = StoredElementBuilder::create('Sw:Box', 'child-1')
            ->withConsumer('product', ContextType::Single)
            ->withConsumer('product.cover', ContextType::Single, scope: ConsumerScope::Root)
            ->build();
        $parent = $this->providerOf('product', BroadcastDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute(
            $parent,
            ['product' => new StubContextStruct('parent-cover')],
            [$child]
        );

        static::assertSame(['product'], array_keys($deliveries[0]->context));
    }

    #[TestDox('reports the key a keyed distribution selected on as a distribution referenced key')]
    public function testKeyedDistributionReportsItsKeyProperty(): void
    {
        $children = [$this->keyedConsumerOf('child-1', 'items', 'present')];
        $parent = $this->providerOf('items', KeyedDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute(
            $parent,
            ['items' => ['present' => 'present-item']],
            $children
        );

        static::assertSame(['data_key'], $deliveries[0]->distributionReferencedKeys);
    }

    #[TestDox('reports no distribution referenced key for a strategy that dereferences none')]
    public function testNonKeyedDistributionReportsNoReferencedKey(): void
    {
        $children = [$this->keyedConsumerOf('child-1', 'product', 'present')];
        $parent = $this->providerOf('product', BroadcastDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute($parent, ['product' => 'product-data'], $children);

        static::assertSame([], $deliveries[0]->distributionReferencedKeys);
    }

    #[TestDox('reports no distribution referenced key for a child that never matched the keyed provider')]
    public function testUnmatchedChildGetsNoDistributionReferencedKey(): void
    {
        $children = [
            $this->keyedConsumerOf('child-1', 'items', 'present'),
            StoredElementBuilder::create('Sw:Text', 'child-2')->withProperty('data_key', 'present')->build(),
        ];
        $parent = $this->providerOf('items', KeyedDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute(
            $parent,
            ['items' => ['present' => 'present-item']],
            $children
        );

        static::assertSame(['data_key'], $deliveries[0]->distributionReferencedKeys);
        static::assertSame([], $deliveries[1]->distributionReferencedKeys);
    }

    /**
     * The deliberate narrowing: a keyed distribution selects on the child's STORED value, so the key it
     * selects by is the same one the distribution-referenced tier renders. A child storing nothing under
     * `data_key` selects nothing, whatever a later stage might have produced under that name.
     */
    #[TestDox('selects on the stored value, so a child storing nothing under the key property matches nothing')]
    public function testKeyedDistributionSelectsOnStoredValuesOnly(): void
    {
        $children = [
            StoredElementBuilder::create('Sw:Box', 'child-1')
                ->withConsumer('items', ContextType::Single)
                ->build(),
        ];
        $parent = $this->providerOf('items', KeyedDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute(
            $parent,
            ['items' => ['present' => 'present-item']],
            $children
        );

        static::assertSame(['items' => null], $deliveries[0]->context);
    }

    /**
     * The gate that makes every other null in this class mean something. `BroadcastDistributionConfig`
     * carries no null check, so without it a null provider value would be written into every consumer key.
     */
    #[TestDox('delivers nothing at all when the provider value is null')]
    public function testNullProviderValueDeliversNothing(): void
    {
        $children = [$this->consumerOf('child-1', 'product')];
        $parent = $this->providerOf('product', BroadcastDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute($parent, ['product' => null], $children);

        static::assertSame([], $deliveries[0]->context);
    }

    #[TestDox('delivers an explicit null to an optional consumer whose path cannot be resolved')]
    public function testOptionalConsumerWithUnresolvablePathGetsAnExplicitNull(): void
    {
        $children = [$this->consumerOf('child-1', 'product.cover')];
        $parent = $this->providerOf('product', BroadcastDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute($parent, ['product' => 'not-a-struct'], $children);

        static::assertSame(['product.cover' => null], $deliveries[0]->context);
    }

    /**
     * @param array<string, mixed> $expectedFirst
     * @param array<string, mixed> $expectedSecond
     */
    #[DataProvider('underSuppliedStrategyProvider')]
    #[TestDox('under-supplies a strategy and delivers its own shape of nothing')]
    public function testEachStrategyHasItsOwnUnderSupplyShape(
        DistributionConfig $config,
        mixed $providerData,
        array $expectedFirst,
        array $expectedSecond,
    ): void {
        $children = [
            $this->keyedConsumerOf('child-1', 'items', 'present'),
            $this->keyedConsumerOf('child-2', 'items', 'absent'),
        ];
        $parent = $this->providerOf('items', $config);

        $deliveries = $this->distributor()->distribute($parent, ['items' => $providerData], $children);

        static::assertSame($expectedFirst, $deliveries[0]->context);
        static::assertSame($expectedSecond, $deliveries[1]->context);
    }

    #[TestDox('returns no deliveries for a parent with no children')]
    public function testParentWithoutChildrenYieldsNoDeliveries(): void
    {
        $parent = $this->providerOf('product', BroadcastDistributionConfig::simple());

        $deliveries = $this->distributor()->distribute($parent, ['product' => 'product-data'], []);

        static::assertSame([], $deliveries);
    }

    #[TestDox('throws naming the offending element when a required consumer path cannot be resolved')]
    public function testRequiredConsumerWithUnresolvablePathThrowsNamingTheElement(): void
    {
        $child = StoredElementBuilder::create('Sw:Box', 'child-1')
            ->withConsumer('product.cover', ContextType::Single, required: true)
            ->build();
        $parent = $this->providerOf('product', BroadcastDistributionConfig::simple());

        $this->expectExceptionObject(ContentSystemException::contextPathNotResolvable(
            'product.cover',
            'child-1',
            'Context data is not a Struct instance'
        ));

        $this->distributor()->distribute($parent, ['product' => 'not-a-struct'], [$child]);
    }

    private function distributor(): ContextDistributor
    {
        return new ContextDistributor(new ContextPathResolver());
    }

    private function providerOf(string $contextKey, DistributionConfig $config): StoredElement
    {
        return StoredElementBuilder::create('Sw:Section', 'parent-1')
            ->withProvider($contextKey, $config)
            ->build();
    }

    private function consumerOf(string $id, string $contextKey): StoredElement
    {
        return StoredElementBuilder::create('Sw:Box', $id)
            ->withConsumer($contextKey, ContextType::Single)
            ->build();
    }

    private function rootScopedConsumerOf(string $id, string $contextKey): StoredElement
    {
        return StoredElementBuilder::create('Sw:Box', $id)
            ->withConsumer($contextKey, ContextType::Single, scope: ConsumerScope::Root)
            ->build();
    }

    private function keyedConsumerOf(string $id, string $contextKey, string $dataKey): StoredElement
    {
        return StoredElementBuilder::create('Sw:Box', $id)
            ->withProperty('data_key', $dataKey)
            ->withConsumer($contextKey, ContextType::Single)
            ->build();
    }
}
