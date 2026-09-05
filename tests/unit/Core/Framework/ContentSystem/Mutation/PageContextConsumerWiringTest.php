<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\PageContextConsumerWiring;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\ContentSystem\Resolution\ResolutionCandidate;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PageContextConsumerWiring::class)]
class PageContextConsumerWiringTest extends TestCase
{
    private const PRODUCT_FQCN = 'Shopware\\Core\\Content\\Product\\SalesChannel\\SalesChannelProductEntity';

    #[TestDox('mirrors a parent-origin resolved reference as a parent-scope consumer on a created element')]
    public function testMirrorsParentOriginAsParentScopeConsumer(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build();

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$element]),
            ['p1' => [$this->reference('product', true, $this->candidate(CandidateOrigin::Parent, 'product'))]],
            ['p1'],
        );

        $consumers = $this->consumers($wired->roots[0]);
        static::assertArrayHasKey('product', $consumers);
        static::assertSame(ContextType::Single, $consumers['product']->type);
        static::assertTrue($consumers['product']->required);
        static::assertSame(ConsumerScope::Parent, $consumers['product']->scope);
        static::assertFalse($consumers['product']->redistribute);
        static::assertNull($consumers['product']->consumerAlias);
        static::assertNull($consumers['product']->propertyAlias);
    }

    #[TestDox('mirrors a root-origin resolved reference as a root-scope consumer on a created element')]
    public function testMirrorsRootOriginAsRootScopeConsumer(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build();

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$element]),
            ['p1' => [$this->reference('product', false, $this->candidate(CandidateOrigin::Root, 'product'))]],
            ['p1'],
        );

        $consumers = $this->consumers($wired->roots[0]);
        static::assertArrayHasKey('product', $consumers);
        static::assertSame(ConsumerScope::Root, $consumers['product']->scope);
        static::assertFalse($consumers['product']->required);
        static::assertFalse($consumers['product']->redistribute);
    }

    #[TestDox('leaves an element outside the created set unwired even when its reference is proven')]
    public function testLeavesUncreatedElementUnwired(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
            ->withConsumer('authored', ContextType::Single)
            ->build();
        $resolutions = ['p1' => [
            $this->reference('product', true, $this->candidate(CandidateOrigin::Parent, 'product')),
            $this->reference('page', true, $this->candidate(CandidateOrigin::Root, 'page')),
        ]];

        $wired = (new PageContextConsumerWiring())->apply(new StoredTree([$element]), $resolutions, ['other-id']);

        static::assertSame($element->contextDefinitions->getAllConsumers(), $this->consumers($wired->roots[0]));
        static::assertSame(['authored'], array_keys($this->consumers($wired->roots[0])));
    }

    #[TestDox('mirrors one consumer per proven reference when an element consumes two context keys')]
    public function testMirrorsOneConsumerPerProvenReference(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build();
        $resolutions = ['p1' => [
            $this->reference('product', true, $this->candidate(CandidateOrigin::Parent, 'product')),
            $this->reference('page', false, $this->candidate(CandidateOrigin::Root, 'page', ContextType::Collection)),
        ]];

        $wired = (new PageContextConsumerWiring())->apply(new StoredTree([$element]), $resolutions, ['p1']);

        $consumers = $this->consumers($wired->roots[0]);
        static::assertSame(['product', 'page'], array_keys($consumers));
        static::assertSame(ConsumerScope::Parent, $consumers['product']->scope);
        static::assertSame(ConsumerScope::Root, $consumers['page']->scope);
        static::assertSame(ContextType::Collection, $consumers['page']->type);
    }

    #[TestDox('keys a cross-key parent mirror by the resolved candidate context key and aliases it to the written property key')]
    public function testKeysTheConsumerByTheCandidateContextKey(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build();

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$element]),
            ['p1' => [$this->reference('crossSellProduct', true, $this->candidate(CandidateOrigin::Parent, 'product'))]],
            ['p1'],
        );

        $consumers = $this->consumers($wired->roots[0]);
        static::assertSame(['product'], array_keys($consumers));
        static::assertSame('crossSellProduct', $consumers['product']->propertyAlias);
        static::assertSame(ConsumerScope::Parent, $consumers['product']->scope);
    }

    #[TestDox('keys a cross-key root mirror by the resolved candidate context key and aliases it to the written property key')]
    public function testKeysTheRootConsumerByTheCandidateContextKey(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build();

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$element]),
            ['p1' => [$this->reference('crossSellProduct', false, $this->candidate(CandidateOrigin::Root, 'product'))]],
            ['p1'],
        );

        $consumers = $this->consumers($wired->roots[0]);
        static::assertSame(['product'], array_keys($consumers));
        static::assertSame(ConsumerScope::Root, $consumers['product']->scope);
        static::assertSame('crossSellProduct', $consumers['product']->propertyAlias);
    }

    #[TestDox('mirrors a cross-key reference even though the element provides the candidate context key, because the provider skip is matched against the written property key')]
    public function testMirrorsCrossKeyReferenceDespiteProvidingTheCandidateContextKey(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->build();

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$element]),
            ['p1' => [$this->reference('crossSellProduct', true, $this->candidate(CandidateOrigin::Parent, 'product'))]],
            ['p1'],
        );

        $consumers = $this->consumers($wired->roots[0]);
        static::assertSame(['product'], array_keys($consumers));
        static::assertSame('crossSellProduct', $consumers['product']->propertyAlias);
        static::assertSame(ConsumerScope::Parent, $consumers['product']->scope);
    }

    #[TestDox('mirrors nothing for a cross-key resolution whose written property key contains a dot, because the decoder rejects a dotted propertyAlias')]
    public function testMirrorsNothingForCrossKeyResolutionWithDottedWrittenKey(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build();
        $tree = new StoredTree([$element]);

        $wired = (new PageContextConsumerWiring())->apply(
            $tree,
            ['p1' => [$this->reference('product.name', true, $this->candidate(CandidateOrigin::Parent, 'product'))]],
            ['p1'],
        );

        static::assertSame($tree, $wired);
        static::assertSame([], $this->consumers($wired->roots[0]));
    }

    #[TestDox('mirrors an equal dotted key as a consumer with no propertyAlias, because a dotted consumer key without an alias is legal')]
    public function testMirrorsEqualDottedKeyWithoutAlias(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build();

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$element]),
            ['p1' => [$this->reference('product.name', true, $this->candidate(CandidateOrigin::Parent, 'product.name'))]],
            ['p1'],
        );

        $consumers = $this->consumers($wired->roots[0]);
        static::assertSame(['product.name'], array_keys($consumers));
        static::assertNull($consumers['product.name']->propertyAlias);
    }

    #[TestDox('mirrors onto a created element nested in a slot, leaving its uncreated ancestors unwired')]
    public function testMirrorsOntoNestedCreatedElement(): void
    {
        $price = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'price')->build();
        $inner = StoredElementBuilder::create('Sw:Grid:Container', 'inner')->withSlot('content', [$price])->build();
        $outer = StoredElementBuilder::create('Sw:Grid:Container', 'outer')->withSlot('content', [$inner])->build();

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$outer]),
            ['price' => [$this->reference('product', false, $this->candidate(CandidateOrigin::Parent, 'product'))]],
            ['price'],
        );

        $wiredOuter = $wired->roots[0];
        $wiredInner = $wiredOuter->slots['content'][0];

        static::assertArrayHasKey('product', $this->consumers($wiredInner->slots['content'][0]));
        static::assertSame([], $this->consumers($wiredInner));
        static::assertSame([], $this->consumers($wiredOuter));
    }

    #[TestDox('wires the created element under the second root, leaving the untouched first root the identical instance')]
    public function testWiresCreatedElementUnderSecondRoot(): void
    {
        $untouched = StoredElementBuilder::create('Sw:Grid:Container', 'root0')->build();
        $target = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'root1')->build();

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$untouched, $target]),
            ['root1' => [$this->reference('product', true, $this->candidate(CandidateOrigin::Parent, 'product'))]],
            ['root1'],
        );

        static::assertSame($untouched, $wired->roots[0]);
        static::assertArrayHasKey('product', $this->consumers($wired->roots[1]));
    }

    #[TestDox('never overwrites a consumer the element already carries under the same key')]
    public function testNeverOverwritesExistingConsumer(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
            ->withConsumer('product', ContextType::Collection, true, false, null, 'item')
            ->build();
        $authored = $this->consumers($element)['product'];

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$element]),
            ['p1' => [$this->reference('product', false, $this->candidate(CandidateOrigin::Parent, 'product'))]],
            ['p1'],
        );

        static::assertSame($authored, $this->consumers($wired->roots[0])['product']);
    }

    #[TestDox('the first of two resolutions sharing a resolved context key wins, and the second is skipped')]
    public function testSameContextKeyResolutionsMirrorOnlyTheFirst(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build();
        $resolutions = ['p1' => [
            $this->reference('firstProperty', false, $this->candidate(CandidateOrigin::Parent, 'product')),
            $this->reference('secondProperty', true, $this->candidate(CandidateOrigin::Parent, 'product')),
        ]];

        $wired = (new PageContextConsumerWiring())->apply(new StoredTree([$element]), $resolutions, ['p1']);

        $consumers = $this->consumers($wired->roots[0]);
        static::assertSame(['product'], array_keys($consumers));
        static::assertFalse($consumers['product']->required);
    }

    #[TestDox('skips a base-key collision against an existing consumer aliased to the same written property')]
    public function testSkipsBaseKeyCollisionViaExistingAlias(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
            ->withConsumer('x', ContextType::Single, false, false, null, 'product')
            ->build();
        $tree = new StoredTree([$element]);

        $wired = (new PageContextConsumerWiring())->apply(
            $tree,
            ['p1' => [$this->reference('product', true, $this->candidate(CandidateOrigin::Parent, 'y'))]],
            ['p1'],
        );

        static::assertSame($tree, $wired);
        static::assertSame(['x'], array_keys($this->consumers($wired->roots[0])));
    }

    #[TestDox('skips a base-key collision against an existing dotted consumer key sharing the first segment')]
    public function testSkipsBaseKeyCollisionViaDottedExistingKey(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
            ->withConsumer('product.name', ContextType::Single)
            ->build();
        $tree = new StoredTree([$element]);

        $wired = (new PageContextConsumerWiring())->apply(
            $tree,
            ['p1' => [$this->reference('product', true, $this->candidate(CandidateOrigin::Parent, 'y'))]],
            ['p1'],
        );

        static::assertSame($tree, $wired);
        static::assertSame(['product.name'], array_keys($this->consumers($wired->roots[0])));
    }

    #[TestDox('mirrors normally when the written property key does not collide with any existing consumer base key')]
    public function testMirrorsWhenNoBaseKeyCollides(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
            ->withConsumer('other', ContextType::Single)
            ->build();

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$element]),
            ['p1' => [$this->reference('product', true, $this->candidate(CandidateOrigin::Parent, 'product'))]],
            ['p1'],
        );

        static::assertSame(['other', 'product'], array_keys($this->consumers($wired->roots[0])));
    }

    #[TestDox('skips a key the element already fills from a data requirement')]
    public function testSkipsKeyFilledByDataRequirement(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
            ->withDataRequirement('product', 'entity', new StubLoaderConfig())
            ->build();

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$element]),
            ['p1' => [$this->reference('product', false, $this->candidate(CandidateOrigin::Parent, 'product'))]],
            ['p1'],
        );

        static::assertArrayNotHasKey('product', $this->consumers($wired->roots[0]));
        static::assertSame([], $this->consumers($wired->roots[0]));
    }

    #[TestDox('skips a key the element itself provides')]
    public function testSkipsSelfProvidedKey(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->build();

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$element]),
            ['p1' => [$this->reference('product', false, $this->candidate(CandidateOrigin::Parent, 'product'))]],
            ['p1'],
        );

        static::assertArrayNotHasKey('product', $this->consumers($wired->roots[0]));
        static::assertSame([], $this->consumers($wired->roots[0]));
    }

    #[TestDox('mirrors nothing for a reference the resolution did not prove')]
    public function testMirrorsNothingForUnprovenReference(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build();
        $tree = new StoredTree([$element]);

        $wired = (new PageContextConsumerWiring())->apply(
            $tree,
            ['p1' => [$this->reference('product', true, null)]],
            ['p1'],
        );

        static::assertSame($tree, $wired);
        static::assertSame([], $this->consumers($wired->roots[0]));
    }

    /**
     * @return iterable<string, array{0: CandidateOrigin}>
     */
    public static function selfFillingOriginProvider(): iterable
    {
        yield 'loader origin' => [CandidateOrigin::Loader];
        yield 'stored origin' => [CandidateOrigin::Stored];
    }

    #[TestDox('mirrors nothing for a reference a loader or the element own wiring already fills')]
    #[DataProvider('selfFillingOriginProvider')]
    public function testMirrorsNothingForSelfFillingOrigin(CandidateOrigin $origin): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build();
        $tree = new StoredTree([$element]);

        $wired = (new PageContextConsumerWiring())->apply(
            $tree,
            ['p1' => [$this->reference('product', true, $this->candidate($origin, 'product'))]],
            ['p1'],
        );

        static::assertSame($tree, $wired);
        static::assertSame([], $this->consumers($wired->roots[0]));
    }

    #[TestDox('mirrors nothing for a primitive resolution carrying a proven candidate')]
    public function testMirrorsNothingForPrimitiveKind(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build();
        $tree = new StoredTree([$element]);
        $primitive = new PropertyResolution(
            'product',
            PropertyKind::Primitive,
            true,
            'string',
            null,
            null,
            $this->candidate(CandidateOrigin::Parent, 'product'),
        );

        $wired = (new PageContextConsumerWiring())->apply($tree, ['p1' => [$primitive]], ['p1']);

        static::assertSame($tree, $wired);
    }

    #[TestDox('mirrors nothing for a proven candidate carrying no context type')]
    public function testMirrorsNothingWithoutContextType(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build();
        $tree = new StoredTree([$element]);
        $candidate = new ResolutionCandidate(CandidateOrigin::Parent, 'product', null, null, DistributionStrategy::Broadcast, null);

        $wired = (new PageContextConsumerWiring())->apply($tree, ['p1' => [$this->reference('product', true, $candidate)]], ['p1']);

        static::assertSame($tree, $wired);
    }

    #[TestDox('mirrors nothing for a proven candidate carrying an empty context key')]
    public function testMirrorsNothingWithoutContextKey(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build();
        $tree = new StoredTree([$element]);
        $candidate = $this->candidate(CandidateOrigin::Parent, '');

        $wired = (new PageContextConsumerWiring())->apply($tree, ['p1' => [$this->reference('product', true, $candidate)]], ['p1']);

        static::assertSame($tree, $wired);
    }

    #[TestDox('returns the identical tree instance when the created set is empty')]
    public function testReturnsIdenticalTreeForEmptyCreatedSet(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build();
        $tree = new StoredTree([$element]);

        $wired = (new PageContextConsumerWiring())->apply(
            $tree,
            ['p1' => [$this->reference('product', true, $this->candidate(CandidateOrigin::Parent, 'product'))]],
            [],
        );

        static::assertSame($tree, $wired);
    }

    #[TestDox('returns the identical tree instance when a created element has no resolutions at all')]
    public function testReturnsIdenticalTreeWhenCreatedElementHasNoResolutions(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build();
        $tree = new StoredTree([$element]);

        $wired = (new PageContextConsumerWiring())->apply($tree, [], ['p1']);

        static::assertSame($tree, $wired);
    }

    /**
     * @return array<string, ContextConsumer>
     */
    private function consumers(StoredElement $element): array
    {
        return $element->contextDefinitions->getAllConsumers();
    }

    private function reference(string $key, bool $required, ?ResolutionCandidate $resolved): PropertyResolution
    {
        return new PropertyResolution($key, PropertyKind::Reference, $required, null, null, self::PRODUCT_FQCN, $resolved);
    }

    private function candidate(CandidateOrigin $origin, string $contextKey, ContextType $type = ContextType::Single): ResolutionCandidate
    {
        return new ResolutionCandidate($origin, $contextKey, null, null, DistributionStrategy::Broadcast, $type);
    }
}
