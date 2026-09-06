<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopware\Core\Framework\ContentSystem\Mutation\PageContextConsumerWiring;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\ContentSystem\Resolution\ResolutionCandidate;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MutationPipeline::class)]
class MutationPipelineTest extends TestCase
{
    #[TestDox('applies the mutation, returns the mutated layout, affected ids and report, and restricts resolutions to the affected elements')]
    public function testRunReturnsMutatedLayoutAndRestrictsResolutionsToAffected(): void
    {
        $tree = $this->inputTree();
        $mutated = new StoredTree([new StoredElement('new-1', 'Sw:Card')]);
        $report = new DiagnosticsReport([]);
        $resolutions = [
            'new-1' => [new PropertyResolution('headline', PropertyKind::Primitive, false, 'string', 'hi')],
            'other' => [new PropertyResolution('title', PropertyKind::Primitive, false, 'string', 'x')],
        ];

        $pipeline = $this->pipeline($this->diagnosticsReturning(new LayoutAnalysis($report, $resolutions)));

        $result = $pipeline->run($this->mutationExpecting($tree, $mutated, ['new-1']), $tree, null);

        static::assertSame($mutated, $result->layout);
        static::assertSame(['new-1'], $result->affectedElementIds);
        static::assertSame($report, $result->diagnostics);
        static::assertSame(['new-1'], array_keys($result->resolutions));
    }

    #[TestDox('passes orphaned subtrees, dropped wiring keys and dropped static property values from the op through to the result')]
    public function testRunCarriesOrphanedDroppedWiringAndDroppedProperties(): void
    {
        $orphan = new StoredElement('orphan', 'Sw:Block');
        $droppedHeadline = StoredValue::ofString('Old headline');

        $pipeline = $this->pipeline($this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), [])));

        $result = $pipeline->run(
            $this->mutation(
                new StoredTree([new StoredElement('el-1', 'Sw:New')]),
                ['el-1'],
                [$orphan],
                ['legacy'],
                ['headline' => $droppedHeadline],
            ),
            $this->inputTree(),
            null,
        );

        static::assertSame([$orphan], $result->orphaned);
        static::assertSame(['legacy'], $result->droppedWiring);
        static::assertSame(['headline' => $droppedHeadline], $result->droppedProperties);
    }

    #[TestDox('forwards the mutated stored roots unconverted, with the root context, to the diagnostics pass')]
    public function testRunForwardsMutatedStoredRootsAndRootContextToDiagnostics(): void
    {
        $mutated = new StoredTree([new StoredElement('new-1', 'Sw:Card')]);
        $rootContext = [new ProvidedContext('product', 'Some\\Entity', ContextType::Single, null, DistributionStrategy::Broadcast)];
        $report = new DiagnosticsReport([]);

        $diagnostics = $this->createMock(LayoutDiagnostics::class);
        $diagnostics->expects($this->once())
            ->method('analyze')
            ->with(static::identicalTo($mutated->roots), static::identicalTo($rootContext))
            ->willReturn(new LayoutAnalysis($report, []));

        $result = $this->pipeline($diagnostics)->run($this->mutation($mutated, ['new-1']), $this->inputTree(), $rootContext);

        static::assertSame($report, $result->diagnostics);
    }

    #[TestDox('forwards the root context to the second diagnostics pass as well as the first')]
    public function testRunForwardsRootContextToBothAnalyzePasses(): void
    {
        $mutated = new StoredTree([StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build()]);
        $rootContext = [new ProvidedContext('product', 'App\\Product', ContextType::Single, null, DistributionStrategy::Broadcast)];

        $diagnostics = $this->createMock(LayoutDiagnostics::class);
        $diagnostics->expects($this->exactly(2))
            ->method('analyze')
            ->with(static::anything(), static::identicalTo($rootContext))
            ->willReturnCallback(fn (array $roots): LayoutAnalysis => new LayoutAnalysis(new DiagnosticsReport([]), $this->productResolutions($roots)));

        $result = $this->pipeline($diagnostics)->run($this->mutation($mutated, ['p1'], created: ['p1']), $this->inputTree(), $rootContext);

        static::assertNotSame($mutated, $result->layout);
    }

    #[TestDox('mirrors the proven consumers of the created elements into the returned layout')]
    public function testRunMirrorsConsumersOfCreatedElements(): void
    {
        $mutated = new StoredTree([StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build()]);

        $pipeline = $this->pipeline($this->diagnosticsResolvingProduct());

        $result = $pipeline->run($this->mutation($mutated, ['p1'], created: ['p1']), $this->inputTree(), null);

        $consumers = $result->layout->roots[0]->contextDefinitions->getAllConsumers();
        static::assertArrayHasKey('product', $consumers);
        static::assertSame(ConsumerScope::Parent, $consumers['product']->scope);
    }

    #[TestDox('re-analyzes the wired tree and assembles the result from that second analysis, not the first')]
    public function testRunReanalyzesTheWiredTree(): void
    {
        $mutated = new StoredTree([StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build()]);
        $secondReport = new DiagnosticsReport([]);

        $analyzed = [];
        $secondPassResolutions = [];
        $diagnostics = $this->createMock(LayoutDiagnostics::class);
        $diagnostics->expects($this->exactly(2))
            ->method('analyze')
            ->willReturnCallback(function (array $roots) use (&$analyzed, &$secondPassResolutions, $secondReport): LayoutAnalysis {
                $analyzed[] = $roots;

                if (\count($analyzed) === 1) {
                    return new LayoutAnalysis(new DiagnosticsReport([]), $this->productResolutions($roots));
                }

                // A distinct marker key on the second pass's resolutions, so a result that paired the second
                // report with the first pass's resolutions (rather than this second payload) fails the assertion.
                $secondPassResolutions = $this->productResolutions($roots, marked: true);

                return new LayoutAnalysis($secondReport, $secondPassResolutions);
            });

        $result = $this->pipeline($diagnostics)->run($this->mutation($mutated, ['p1'], created: ['p1']), $this->inputTree(), null);

        static::assertSame($secondReport, $result->diagnostics);
        static::assertSame($mutated->roots, $analyzed[0]);
        static::assertSame($result->layout->roots, $analyzed[1]);
        static::assertNotSame($mutated->roots, $analyzed[1]);
        static::assertSame($secondPassResolutions, $result->resolutions);
    }

    #[TestDox('leaves an unwired but still provable element unwired through a non-creating move')]
    public function testUnwiredConsumerSurvivesANonCreatingMove(): void
    {
        $tree = $this->unwiredTree();

        $diagnostics = $this->createMock(LayoutDiagnostics::class);
        $diagnostics->expects($this->once())
            ->method('analyze')
            ->willReturnCallback(fn (array $roots): LayoutAnalysis => new LayoutAnalysis(new DiagnosticsReport([]), $this->productResolutions($roots)));

        $result = $this->pipeline($diagnostics)->run(new MoveElement('el-1'), $tree, null);

        $moved = $result->layout->roots[1];
        static::assertSame('el-1', $moved->id);
        static::assertSame([], $moved->contextDefinitions->getAllConsumers());
    }

    #[TestDox('restores the consumer of an unwired element when a replace re-scaffolds its node')]
    public function testReplaceRestoresTheUnwiredConsumer(): void
    {
        $replace = new ReplaceElement(
            $this->typeRegistry(),
            'el-1',
            'Sw:New',
            static::createStub(AbstractContentSystemBindingSpecificationRegistry::class),
            new BindingApplicator(static::createStub(DataLoaderConfigSerializerProvider::class)),
        );

        $result = $this->pipeline($this->diagnosticsResolvingProduct())->run($replace, $this->unwiredTree(), null);

        $replaced = $result->layout->roots[0]->slots['content'][0];
        static::assertSame('el-1', $replaced->id);
        static::assertArrayHasKey('product', $replaced->contextDefinitions->getAllConsumers());
    }

    #[TestDox('wires the clone of an unwired element and leaves the unwired original alone')]
    public function testDuplicateWiresTheCloneOnly(): void
    {
        $result = $this->pipeline($this->diagnosticsResolvingProduct())->run(new DuplicateElement('el-1'), $this->unwiredTree(), null);

        $children = $result->layout->roots[0]->slots['content'];
        static::assertCount(2, $children);
        static::assertSame('el-1', $children[0]->id);
        static::assertSame([], $children[0]->contextDefinitions->getAllConsumers());
        static::assertNotSame('el-1', $children[1]->id);
        static::assertArrayHasKey('product', $children[1]->contextDefinitions->getAllConsumers());
    }

    #[TestDox('returns no resolutions when the mutation affects nothing')]
    public function testRunReturnsEmptyResolutionsWhenNothingAffected(): void
    {
        $resolutions = [
            'new-1' => [new PropertyResolution('headline', PropertyKind::Primitive, false, 'string', 'hi')],
        ];

        $pipeline = $this->pipeline($this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), $resolutions)));

        $result = $pipeline->run($this->mutation(new StoredTree([new StoredElement('new-1', 'Sw:Card')]), []), $this->inputTree(), null);

        static::assertSame([], $result->resolutions);
    }

    #[TestDox('runs a single analysis pass when a created element proves no consumer to mirror')]
    public function testRunAnalyzesOnceWhenNothingIsWired(): void
    {
        $mutated = new StoredTree([StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build()]);
        $resolutions = [
            'p1' => [new PropertyResolution('headline', PropertyKind::Primitive, false, 'string', 'hi')],
        ];

        $diagnostics = $this->createMock(LayoutDiagnostics::class);
        $diagnostics->expects($this->once())
            ->method('analyze')
            ->willReturn(new LayoutAnalysis(new DiagnosticsReport([]), $resolutions));

        $result = $this->pipeline($diagnostics)->run($this->mutation($mutated, ['p1'], created: ['p1']), $this->inputTree(), null);

        static::assertSame($mutated, $result->layout);
    }

    private function pipeline(LayoutDiagnostics $diagnostics): MutationPipeline
    {
        return new MutationPipeline($diagnostics, new PageContextConsumerWiring());
    }

    private function inputTree(): StoredTree
    {
        return new StoredTree([new StoredElement('el-1', 'Sw:Block')]);
    }

    /**
     * A container holding one element whose `product` reference stays provable while the element carries no
     * consumer for it, which is the state an explicit unwiring leaves behind.
     */
    private function unwiredTree(): StoredTree
    {
        $element = StoredElementBuilder::create('Sw:Old', 'el-1')->build();

        return new StoredTree([
            StoredElementBuilder::create('Sw:Grid:Container', 'parent')->withSlot('content', [$element])->build(),
        ]);
    }

    private function typeRegistry(): AbstractContentSystemElementTypeRegistry
    {
        $specs = ['Sw:New' => ContentSystemElementTypeSpecificationBuilder::create('Sw:New')->build()];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return $registry;
    }

    /**
     * @param array<StoredElement> $roots
     *
     * @return array<string, list<PropertyResolution>>
     */
    private function productResolutions(array $roots, bool $marked = false): array
    {
        $resolutions = [];
        $key = $marked ? 'product-second-pass' : 'product';

        foreach ((new StoredTree(array_values($roots)))->ids() as $id) {
            $resolutions[$id] = [new PropertyResolution(
                $key,
                PropertyKind::Reference,
                true,
                null,
                null,
                'App\\Product',
                new ResolutionCandidate(CandidateOrigin::Parent, $key, null, null, DistributionStrategy::Broadcast, ContextType::Single),
            )];
        }

        return $resolutions;
    }

    private function diagnosticsResolvingProduct(): LayoutDiagnostics
    {
        $diagnostics = static::createStub(LayoutDiagnostics::class);
        $diagnostics->method('analyze')->willReturnCallback(
            fn (array $roots): LayoutAnalysis => new LayoutAnalysis(new DiagnosticsReport([]), $this->productResolutions($roots))
        );

        return $diagnostics;
    }

    /**
     * @param list<StoredElement> $orphaned
     * @param list<string> $affected
     * @param list<string> $droppedWiring
     * @param array<string, StoredValue> $droppedProperties
     * @param list<string> $created
     */
    private function mutation(
        StoredTree $appliedTree,
        array $affected,
        array $orphaned = [],
        array $droppedWiring = [],
        array $droppedProperties = [],
        array $created = [],
    ): LayoutMutation {
        $mutation = static::createStub(LayoutMutation::class);
        $mutation->method('apply')->willReturn($appliedTree);

        $this->stubReporters($mutation, $affected, $orphaned, $droppedWiring, $droppedProperties, $created);

        return $mutation;
    }

    /**
     * @param list<StoredElement> $orphaned
     * @param list<string> $affected
     * @param list<string> $droppedWiring
     * @param array<string, StoredValue> $droppedProperties
     * @param list<string> $created
     */
    private function mutationExpecting(
        StoredTree $expectedInputTree,
        StoredTree $appliedTree,
        array $affected,
        array $orphaned = [],
        array $droppedWiring = [],
        array $droppedProperties = [],
        array $created = [],
    ): LayoutMutation {
        $mutation = $this->createMock(LayoutMutation::class);
        $mutation->expects($this->once())
            ->method('apply')
            ->with(static::identicalTo($expectedInputTree))
            ->willReturn($appliedTree);

        $this->stubReporters($mutation, $affected, $orphaned, $droppedWiring, $droppedProperties, $created);

        return $mutation;
    }

    /**
     * @param Stub&LayoutMutation $mutation
     * @param list<string> $affected
     * @param list<StoredElement> $orphaned
     * @param list<string> $droppedWiring
     * @param array<string, StoredValue> $droppedProperties
     * @param list<string> $created
     */
    private function stubReporters(
        Stub $mutation,
        array $affected,
        array $orphaned,
        array $droppedWiring,
        array $droppedProperties,
        array $created,
    ): void {
        $mutation->method('affected')->willReturn($affected);
        $mutation->method('created')->willReturn($created);
        $mutation->method('orphaned')->willReturn($orphaned);
        $mutation->method('droppedWiring')->willReturn($droppedWiring);
        $mutation->method('droppedProperties')->willReturn($droppedProperties);
    }

    private function diagnosticsReturning(LayoutAnalysis $analysis): LayoutDiagnostics
    {
        $diagnostics = static::createStub(LayoutDiagnostics::class);
        $diagnostics->method('analyze')->willReturn($analysis);

        return $diagnostics;
    }
}
