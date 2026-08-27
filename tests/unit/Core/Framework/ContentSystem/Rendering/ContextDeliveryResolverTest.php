<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\IndexedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\KeyedDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDeliveryResolver;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDistributor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

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

        $index = $this->resolver()->resolve([$root], []);

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

        $index = $this->resolver()->resolve([$root], []);

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

        $index = $this->resolver()->resolve($forest, []);

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

        $index = $this->resolver()->resolve([$root], []);

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

        $index = $this->resolver()->resolve([$root], []);

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

        $index = $this->resolver()->resolve([$root], ['root-1' => ['product' => 'loaded-data']]);

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

        $index = $this->resolver()->resolve([$root], ['root-1' => ['product' => 'loaded-data']]);

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

        $index = $this->resolver()->resolve([$root], []);

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

        $index = $this->resolver()->resolve([$root], ['root-1' => ['product' => null]]);

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

        $index = $this->resolver()->resolve([$root], []);

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

        $index = $this->resolver()->resolve([$root], []);

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

        $this->resolver()->resolve([$root], []);

        static::assertSame([], $child->properties());
        static::assertSame(['product' => 'product-data'], array_map(
            static fn ($value): mixed => $value->jsonSerialize(),
            $root->properties()
        ));
    }

    #[TestDox('returns an empty index for an empty forest')]
    public function testEmptyForestYieldsAnEmptyIndex(): void
    {
        $index = $this->resolver()->resolve([], []);

        static::assertSame([], $index->all());
    }

    #[TestDox('reports an element id it never walked as absent rather than as having received nothing')]
    public function testAnIdOutsideTheForestIsAbsentFromTheIndex(): void
    {
        $index = $this->resolver()->resolve([$this->providerRoot('root-1', 'child-1', 'product-data')], []);

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

        $this->resolver()->resolve([$root], []);
    }

    private function resolver(): ContextDeliveryResolver
    {
        return new ContextDeliveryResolver(new ContextDistributor(new ContextPathResolver()));
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
