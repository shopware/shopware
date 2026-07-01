<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\ApplicableBindingsResolver;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(MutationPipeline::class)]
class MutationPipelineTest extends TestCase
{
    #[TestDox('applies the mutation and returns the mutated layout, affected ids and report')]
    public function testRunReturnsMutatedLayout(): void
    {
        $mutated = new ContentElement('new-1', 'Sw:Card');
        $report = new DiagnosticsReport([]);

        $pipeline = new MutationPipeline($this->diagnosticsReturning(new LayoutAnalysis($report, ['new-1' => []])), static::createStub(ApplicableBindingsResolver::class));

        $result = $pipeline->run($this->mutation([$mutated], ['new-1']), [new ContentElement('el-1', 'Sw:Block')], null);

        static::assertSame([$mutated], $result->layout);
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

        $pipeline = new MutationPipeline($this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), $resolutions)), static::createStub(ApplicableBindingsResolver::class));

        $result = $pipeline->run($this->mutation([new ContentElement('new-1', 'Sw:Card')], ['new-1']), [new ContentElement('el-1', 'Sw:Block')], null);

        static::assertSame(['new-1'], array_keys($result->resolutions));
    }

    #[TestDox('passes orphaned subtrees from the op through to the result')]
    public function testRunCarriesOrphaned(): void
    {
        $orphan = new ContentElement('orphan', 'Sw:Block');

        $pipeline = new MutationPipeline($this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), [])), static::createStub(ApplicableBindingsResolver::class));

        $result = $pipeline->run($this->mutation([new ContentElement('el-1', 'Sw:New')], ['el-1'], [$orphan]), [new ContentElement('el-1', 'Sw:Block')], null);

        static::assertSame([$orphan], $result->orphaned);
    }

    #[TestDox('passes dropped wiring keys from the op through to the result')]
    public function testRunCarriesDroppedWiring(): void
    {
        $pipeline = new MutationPipeline($this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), [])), static::createStub(ApplicableBindingsResolver::class));

        $result = $pipeline->run($this->mutation([new ContentElement('el-1', 'Sw:New')], ['el-1'], [], ['legacy']), [new ContentElement('el-1', 'Sw:Block')], null);

        static::assertSame(['legacy'], $result->droppedWiring);
    }

    #[TestDox('forwards the mutated tree, root context and context to the diagnostics pass')]
    public function testRunForwardsArgumentsToDiagnostics(): void
    {
        $mutated = new ContentElement('new-1', 'Sw:Card');
        $rootContext = [new ProvidedContext('product', 'Some\\Entity', ContextType::Single, null, DistributionStrategy::Broadcast)];
        $context = Context::createDefaultContext();

        $diagnostics = $this->createMock(LayoutDiagnostics::class);
        $diagnostics->expects($this->once())
            ->method('analyze')
            ->with([$mutated], $rootContext, $context)
            ->willReturn(new LayoutAnalysis(new DiagnosticsReport([]), []));

        $pipeline = new MutationPipeline($diagnostics, static::createStub(ApplicableBindingsResolver::class));

        $pipeline->run($this->mutation([$mutated], ['new-1']), [new ContentElement('el-1', 'Sw:Block')], $rootContext, $context);
    }

    /**
     * @param list<ContentElement> $appliedTree
     * @param list<string> $affected
     * @param list<ContentElement> $orphaned
     * @param list<string> $droppedWiring
     */
    private function mutation(array $appliedTree, array $affected, array $orphaned = [], array $droppedWiring = []): LayoutMutation
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
