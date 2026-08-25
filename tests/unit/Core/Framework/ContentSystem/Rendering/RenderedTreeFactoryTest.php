<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Output\Index\LoaderValueIdentity;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueFingerprinter;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueOrigin;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueProvenance;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDelivery;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDeliveryIndex;
use Shopware\Core\Framework\ContentSystem\Rendering\LoweringResult;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElementFactory;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedTreeFactory;
use Shopware\Core\Framework\ContentSystem\Rendering\ResolvedLoaderValue;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RenderedTreeFactory::class)]
#[CoversClass(ContextDeliveryIndex::class)]
class RenderedTreeFactoryTest extends TestCase
{
    #[TestDox('mints one rendered root per stored root, in order')]
    public function testMintsOneRootPerStoredRoot(): void
    {
        $forest = [
            StoredElementBuilder::create('Sw:Text', 'root-1')->build(),
            StoredElementBuilder::create('Sw:Text', 'root-2')->build(),
        ];

        $tree = $this->mint($forest, $this->indexFor([]), [], RenderingMode::FULL);

        static::assertCount(2, $tree);
        static::assertSame('root-1', $tree[0]->id);
        static::assertSame('root-2', $tree[1]->id);
    }

    #[TestDox('mints children into their slots, keeping slot order and child order within a slot')]
    public function testSlotAndChildOrderSurvive(): void
    {
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withSlot('left', [
                StoredElementBuilder::create('Sw:Text', 'child-1')->build(),
                StoredElementBuilder::create('Sw:Text', 'child-2')->build(),
            ])
            ->withSlot('right', [StoredElementBuilder::create('Sw:Text', 'child-3')->build()])
            ->build();

        $tree = $this->mint([$root], $this->indexFor([]), [], RenderingMode::FULL);

        static::assertSame(['left', 'right'], array_keys($tree[0]->slots));
        static::assertSame(['child-1', 'child-2'], array_map(
            static fn (RenderedElement $element): string => $element->id,
            $tree[0]->slots['left']
        ));
        static::assertSame(['child-3'], array_map(
            static fn (RenderedElement $element): string => $element->id,
            $tree[0]->slots['right']
        ));
    }

    #[TestDox('nests rendered elements at every depth')]
    public function testNestsAtEveryDepth(): void
    {
        $root = $this->threeLevelTree();

        $tree = $this->mint([$root], $this->indexFor([]), [], RenderingMode::FULL);

        static::assertSame('grandchild-1', $tree[0]->slots['main'][0]->slots['inner'][0]->id);
    }

    /**
     * The property that justifies one fold rather than two traversals. Everything structural is compared at
     * once, so a mode branch reaching anything but the mint call shows up here — and the property assertion
     * below keeps the test from passing if the two modes stopped differing at all.
     */
    #[TestDox('produces the same structure in both modes, differing only in properties')]
    public function testBothModesProduceTheSameStructure(): void
    {
        $root = $this->threeLevelTree();
        $index = $this->indexFor(['grandchild-1' => new ContextDelivery('grandchild-1', ['headline' => 'delivered'])]);

        $full = $this->mint([$root], $index, [], RenderingMode::FULL);
        $skeleton = $this->mint([$root], $index, [], RenderingMode::SKELETON);

        static::assertSame($this->structureOf($full[0]), $this->structureOf($skeleton[0]));
        static::assertSame(
            ['headline' => 'delivered'],
            $full[0]->slots['main'][0]->slots['inner'][0]->properties
        );
        static::assertSame([], $skeleton[0]->slots['main'][0]->slots['inner'][0]->properties);
    }

    #[TestDox('mints no properties at all in skeleton mode')]
    public function testSkeletonModeMintsNoProperties(): void
    {
        $root = StoredElementBuilder::create('Sw:Text', 'root-1')
            ->withProperty('headline', 'stored')
            ->build();

        $tree = $this->mint([$root], $this->indexFor([]), [], RenderingMode::SKELETON);

        static::assertSame([], $tree[0]->properties);
    }

    #[TestDox('carries the delivered context of each element onto that element')]
    public function testDeliveredContextReachesItsOwnElement(): void
    {
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withSlot('main', [
                StoredElementBuilder::create('Sw:Text', 'child-1')->build(),
                StoredElementBuilder::create('Sw:Text', 'child-2')->build(),
            ])
            ->build();
        $index = $this->indexFor([
            'child-1' => new ContextDelivery('child-1', ['headline' => 'first']),
            'child-2' => new ContextDelivery('child-2', ['headline' => 'second']),
        ]);

        $tree = $this->mint([$root], $index, [], RenderingMode::FULL);

        static::assertSame(['headline' => 'first'], $tree[0]->slots['main'][0]->properties);
        static::assertSame(['headline' => 'second'], $tree[0]->slots['main'][1]->properties);
    }

    #[TestDox('carries a loader resolved value onto the element that resolved it')]
    public function testLoaderValuesReachTheirOwnElement(): void
    {
        $loaded = new StubStruct();
        $root = StoredElementBuilder::create('Sw:Product', 'root-1')
            ->withDataRequirement('product', 'product', new StubLoaderConfig())
            ->build();

        $tree = $this->mint(
            [$root],
            $this->indexFor([]),
            ['root-1' => ['product' => $loaded]],
            RenderingMode::FULL
        );

        static::assertSame(['product' => $loaded], $tree[0]->properties);
    }

    #[TestDox('collects the provenance of every element it minted, keyed by element id')]
    public function testProvenanceIsCollectedAcrossTheWholeFold(): void
    {
        $child = StoredElementBuilder::create('Sw:Text', 'child-1')
            ->withProperty('headline', 'Nested')
            ->build();
        $root = StoredElementBuilder::create('Sw:Product', 'root-1')
            ->withDataRequirement('product', 'product', new StubLoaderConfig())
            ->withSlot('main', [$child])
            ->build();

        $result = $this->mintResult(
            [$root],
            $this->indexFor([]),
            ['root-1' => ['product' => new StubStruct()]],
            RenderingMode::FULL
        );

        // Both depths are present, each under its own element id, so a fold that recorded only the roots or
        // only the leaves fails here.
        static::assertSame(['child-1', 'root-1'], $this->sortedKeys($result->provenance));
        static::assertSame(ValueOrigin::DeclaredAuthored, $result->provenance['child-1']['headline']->origin);
        static::assertSame(ValueOrigin::LoaderResolved, $result->provenance['root-1']['product']->origin);
    }

    #[TestDox('records no provenance entry for an element that carries no properties')]
    public function testProvenanceOmitsAPropertylessElement(): void
    {
        $root = StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withSlot('main', [StoredElementBuilder::create('Sw:Text', 'child-1')->withProperty('headline', 'Nested')->build()])
            ->build();

        $result = $this->mintResult([$root], $this->indexFor([]), [], RenderingMode::FULL);

        static::assertSame(['child-1'], $this->sortedKeys($result->provenance));
    }

    /**
     * A stored key a keyed distribution named survives onto the rendered element only because the index's
     * referenced-key list is passed through. `data_key` is declared by no type, so the declared tier cannot
     * carry it and this is the only route it has.
     */
    #[TestDox('carries a distribution referenced key onto the rendered element')]
    public function testDistributionReferencedKeysReachTheRenderedElement(): void
    {
        $root = StoredElementBuilder::create('Sw:Text', 'root-1')
            ->withProperty('data_key', 'present')
            ->build();
        $index = $this->indexFor(['root-1' => new ContextDelivery('root-1', [], ['data_key'])]);

        $tree = $this->mint([$root], $index, [], RenderingMode::FULL);

        static::assertSame(['data_key' => 'present'], $tree[0]->properties);
    }

    #[TestDox('keeps the stored style on the rendered element in both modes')]
    public function testStyleSurvivesInBothModes(): void
    {
        $style = new ElementStyle(['col-span' => 6]);
        $root = StoredElementBuilder::create('Sw:Text', 'root-1')->withStyle($style)->build();
        $index = $this->indexFor([]);

        $full = $this->mint([$root], $index, [], RenderingMode::FULL);
        $skeleton = $this->mint([$root], $index, [], RenderingMode::SKELETON);

        static::assertSame($style, $full[0]->style);
        static::assertSame($style, $skeleton[0]->style);
    }

    #[TestDox('fails naming the element when the index was built from a different forest')]
    public function testMissingDeliveryFailsNamingTheElement(): void
    {
        $root = StoredElementBuilder::create('Sw:Text', 'root-1')->build();

        $this->expectExceptionObject(ContentSystemException::contextDeliveryMissing('root-1'));

        $this->mint([$root], new ContextDeliveryIndex(), [], RenderingMode::FULL);
    }

    #[TestDox('renders a skeleton without consulting the delivery index at all')]
    public function testSkeletonModeNeedsNoDeliveries(): void
    {
        $root = StoredElementBuilder::create('Sw:Text', 'root-1')->build();

        $tree = $this->mint([$root], new ContextDeliveryIndex(), [], RenderingMode::SKELETON);

        static::assertSame('root-1', $tree[0]->id);
    }

    #[TestDox('returns no rendered elements for an empty forest')]
    public function testEmptyForestYieldsAnEmptyTree(): void
    {
        $result = $this->mintResult([], new ContextDeliveryIndex(), [], RenderingMode::FULL);
        $tree = $result->tree;

        static::assertSame([], $result->provenance);

        static::assertSame([], $tree);
    }

    /**
     * @param array<string, array<string, ValueProvenance>> $provenance
     *
     * @return list<string>
     */
    private function sortedKeys(array $provenance): array
    {
        $keys = array_keys($provenance);
        sort($keys);

        return $keys;
    }

    /**
     * Everything a rendered element carries EXCEPT its properties, which is the one thing the two modes are
     * allowed to differ in. Style compares by identity because both modes pass the same stored instance.
     *
     * @return array<string, mixed>
     */
    private function structureOf(RenderedElement $element): array
    {
        $slots = [];
        foreach ($element->slots as $name => $children) {
            $slots[$name] = array_map($this->structureOf(...), $children);
        }

        return [
            'id' => $element->id,
            'component' => $element->component,
            'style' => $element->style,
            'slots' => $slots,
        ];
    }

    private function threeLevelTree(): StoredElement
    {
        $grandchild = StoredElementBuilder::create('Sw:Text', 'grandchild-1')->build();
        $child = StoredElementBuilder::create('Sw:Section', 'child-1')
            ->withSlot('inner', [$grandchild])
            ->build();

        return StoredElementBuilder::create('Sw:Section', 'root-1')
            ->withSlot('main', [$child])
            ->build();
    }

    /**
     * @param array<string, ContextDelivery> $deliveries
     */
    private function indexFor(array $deliveries): ContextDeliveryIndex
    {
        foreach (['root-1', 'root-2', 'child-1', 'child-2', 'child-3', 'grandchild-1'] as $id) {
            $deliveries[$id] ??= new ContextDelivery($id);
        }

        return new ContextDeliveryIndex($deliveries);
    }

    /**
     * The rendered forest alone, for every test about structure and property maps. Raw loader values are
     * wrapped in their identity here so those tests keep saying what they are about.
     *
     * @param list<StoredElement> $forest
     * @param array<string, array<string, mixed>> $loaderValues
     *
     * @return list<RenderedElement>
     */
    private function mint(
        array $forest,
        ContextDeliveryIndex $deliveries,
        array $loaderValues,
        RenderingMode $mode,
    ): array {
        return $this->mintResult($forest, $deliveries, $loaderValues, $mode)->tree;
    }

    /**
     * @param list<StoredElement> $forest
     * @param array<string, array<string, mixed>> $loaderValues
     */
    private function mintResult(
        array $forest,
        ContextDeliveryIndex $deliveries,
        array $loaderValues,
        RenderingMode $mode,
    ): LoweringResult {
        $fingerprinter = new ValueFingerprinter();

        $resolved = array_map(
            static fn (array $values): array => array_map(
                static fn (mixed $value): ResolvedLoaderValue => new ResolvedLoaderValue(
                    $value,
                    new LoaderValueIdentity('stub', 'config-hash', 'inputs-hash', $fingerprinter->fingerprint($value)),
                ),
                $values
            ),
            $loaderValues
        );

        return $this->factory()->create($forest, $deliveries, $resolved, $mode);
    }

    private function factory(): RenderedTreeFactory
    {
        $specs = [
            'Sw:Text' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Text')
                ->primitive('headline', 'string')
                ->build(),
            'Sw:Section' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Section')->build(),
            'Sw:Product' => ContentSystemElementTypeSpecificationBuilder::create('Sw:Product')
                ->reference('product', StubStruct::class)
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(
            static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]
        );

        return new RenderedTreeFactory(new RenderedElementFactory($registry));
    }
}
