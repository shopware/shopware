<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Shopware\Core\Framework\ContentSystem\Mutation\PageContextConsumerWiring;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Log\Package;

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

        $result = $pipeline->run($this->mutation($mutated, ['new-1'], expectedInputTree: $tree), $tree, null);

        static::assertSame($mutated, $result->layout);
        static::assertSame(['new-1'], $result->affectedElementIds);
        static::assertSame($report, $result->diagnostics);
        static::assertSame(['new-1'], array_keys($result->resolutions));
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

    #[TestDox('wires the page-context consumers into the returned layout')]
    public function testRunWiresContextConsumers(): void
    {
        $price = new StoredElement('p1', 'Sw:Product:PriceDisplay');
        $resolutions = ['p1' => [new PropertyResolution('product', PropertyKind::Reference, false, null, null, 'App\\Product')]];
        $rootContext = [new ProvidedContext('product', 'App\\Product', ContextType::Single, null, DistributionStrategy::Broadcast)];

        $pipeline = $this->pipeline($this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), $resolutions)));

        $result = $pipeline->run($this->mutation(new StoredTree([$price]), ['p1']), $this->inputTree(), $rootContext);

        static::assertArrayHasKey('product', $result->layout->roots[0]->contextDefinitions->getAllConsumers());
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
     * @param list<StoredElement> $orphaned
     * @param list<string> $affected
     * @param list<string> $droppedWiring
     * @param array<string, StoredValue> $droppedProperties
     */
    private function mutation(
        StoredTree $appliedTree,
        array $affected,
        array $orphaned = [],
        array $droppedWiring = [],
        array $droppedProperties = [],
        ?StoredTree $expectedInputTree = null,
    ): LayoutMutation {
        if ($expectedInputTree !== null) {
            $mutation = $this->createMock(LayoutMutation::class);
            $mutation->expects($this->once())
                ->method('apply')
                ->with(static::identicalTo($expectedInputTree))
                ->willReturn($appliedTree);
        } else {
            $mutation = static::createStub(LayoutMutation::class);
            $mutation->method('apply')->willReturn($appliedTree);
        }

        $mutation->method('affected')->willReturn($affected);
        $mutation->method('orphaned')->willReturn($orphaned);
        $mutation->method('droppedWiring')->willReturn($droppedWiring);
        $mutation->method('droppedProperties')->willReturn($droppedProperties);

        return $mutation;
    }

    private function diagnosticsReturning(LayoutAnalysis $analysis): LayoutDiagnostics
    {
        $diagnostics = static::createStub(LayoutDiagnostics::class);
        $diagnostics->method('analyze')->willReturn($analysis);

        return $diagnostics;
    }
}
