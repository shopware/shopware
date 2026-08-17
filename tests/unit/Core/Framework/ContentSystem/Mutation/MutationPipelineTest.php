<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElementLowering;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationPipeline;
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
    #[TestDox('applies the mutation and returns the mutated layout, affected ids and report')]
    public function testRunReturnsMutatedLayout(): void
    {
        $mutated = new StoredTree([new StoredElement('new-1', 'Sw:Card')]);
        $report = new DiagnosticsReport([]);

        $pipeline = $this->pipeline($this->diagnosticsReturning(new LayoutAnalysis($report, ['new-1' => []])));

        $result = $pipeline->run($this->mutation($mutated, ['new-1']), $this->inputTree(), null);

        static::assertSame($mutated, $result->layout);
        static::assertSame(['new-1'], $result->affectedElementIds);
        static::assertSame($report, $result->diagnostics);
    }

    #[TestDox('restricts the returned resolutions to the affected elements')]
    public function testRunRestrictsResolutionsToAffected(): void
    {
        $resolutions = [
            'new-1' => [new PropertyResolution('headline', PropertyKind::Primitive, false, 'string', 'hi')],
            'other' => [new PropertyResolution('title', PropertyKind::Primitive, false, 'string', 'x')],
        ];

        $pipeline = $this->pipeline($this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), $resolutions)));

        $result = $pipeline->run($this->mutation(new StoredTree([new StoredElement('new-1', 'Sw:Card')]), ['new-1']), $this->inputTree(), null);

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

    #[TestDox('passes orphaned subtrees from the op through to the result')]
    public function testRunCarriesOrphaned(): void
    {
        $orphan = new StoredElement('orphan', 'Sw:Block');

        $pipeline = $this->pipeline($this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), [])));

        $result = $pipeline->run($this->mutation(new StoredTree([new StoredElement('el-1', 'Sw:New')]), ['el-1'], [$orphan]), $this->inputTree(), null);

        static::assertSame([$orphan], $result->orphaned);
    }

    #[TestDox('passes dropped wiring keys from the op through to the result')]
    public function testRunCarriesDroppedWiring(): void
    {
        $pipeline = $this->pipeline($this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), [])));

        $result = $pipeline->run($this->mutation(new StoredTree([new StoredElement('el-1', 'Sw:New')]), ['el-1'], [], ['legacy']), $this->inputTree(), null);

        static::assertSame(['legacy'], $result->droppedWiring);
    }

    #[TestDox('lowers the mutated tree and forwards it with the root context to the diagnostics pass')]
    public function testRunForwardsLoweredTreeAndRootContextToDiagnostics(): void
    {
        $mutated = new StoredTree([new StoredElement('new-1', 'Sw:Card')]);
        $rootContext = [new ProvidedContext('product', 'Some\\Entity', ContextType::Single, null, DistributionStrategy::Broadcast)];

        $diagnostics = $this->createMock(LayoutDiagnostics::class);
        $diagnostics->expects($this->once())
            ->method('analyze')
            ->with(static::callback(static function (array $tree): bool {
                static::assertCount(1, $tree);
                static::assertInstanceOf(ContentElement::class, $tree[0]);
                static::assertSame('new-1', $tree[0]->getId());

                return true;
            }), $rootContext)
            ->willReturn(new LayoutAnalysis(new DiagnosticsReport([]), []));

        $this->pipeline($diagnostics)->run($this->mutation($mutated, ['new-1']), $this->inputTree(), $rootContext);
    }

    private function pipeline(LayoutDiagnostics $diagnostics): MutationPipeline
    {
        return new MutationPipeline($diagnostics, new ContentElementLowering());
    }

    private function inputTree(): StoredTree
    {
        return new StoredTree([new StoredElement('el-1', 'Sw:Block')]);
    }

    /**
     * @param list<StoredElement> $orphaned
     * @param list<string> $affected
     * @param list<string> $droppedWiring
     */
    private function mutation(StoredTree $appliedTree, array $affected, array $orphaned = [], array $droppedWiring = []): LayoutMutation
    {
        $mutation = static::createStub(LayoutMutation::class);
        $mutation->method('apply')->willReturn($appliedTree);
        $mutation->method('affected')->willReturn($affected);
        $mutation->method('orphaned')->willReturn($orphaned);
        $mutation->method('droppedWiring')->willReturn($droppedWiring);

        return $mutation;
    }

    private function diagnosticsReturning(LayoutAnalysis $analysis): LayoutDiagnostics
    {
        $diagnostics = static::createStub(LayoutDiagnostics::class);
        $diagnostics->method('analyze')->willReturn($analysis);

        return $diagnostics;
    }
}
