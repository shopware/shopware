<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
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
    #[TestDox('decodes, applies the mutation and returns the mutated layout, affected ids and report')]
    public function testRunReturnsMutatedLayout(): void
    {
        $mutated = new ContentElement('new-1', 'Sw:Card');
        $report = new DiagnosticsReport([]);

        $pipeline = new MutationPipeline(
            $this->serializerDecoding(new ContentElement('el-1', 'Sw:Block')),
            $this->diagnosticsReturning(new LayoutAnalysis($report, ['new-1' => []])),
        );

        $result = $pipeline->run($this->mutation([$mutated], ['new-1']), [['id' => 'el-1', 'component' => 'Sw:Block']], null);

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

        $pipeline = new MutationPipeline(
            $this->serializerDecoding(new ContentElement('el-1', 'Sw:Block')),
            $this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), $resolutions)),
        );

        $result = $pipeline->run($this->mutation([new ContentElement('new-1', 'Sw:Card')], ['new-1']), [['id' => 'el-1', 'component' => 'Sw:Block']], null);

        static::assertSame(['new-1'], array_keys($result->resolutions));
    }

    #[TestDox('passes orphaned subtrees from the op through to the result')]
    public function testRunCarriesOrphaned(): void
    {
        $orphan = new ContentElement('orphan', 'Sw:Block');

        $pipeline = new MutationPipeline(
            $this->serializerDecoding(new ContentElement('el-1', 'Sw:Block')),
            $this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), [])),
        );

        $result = $pipeline->run($this->mutation([new ContentElement('el-1', 'Sw:New')], ['el-1'], [$orphan]), [['id' => 'el-1', 'component' => 'Sw:Block']], null);

        static::assertSame([$orphan], $result->orphaned);
    }

    #[TestDox('passes dropped wiring keys from the op through to the result')]
    public function testRunCarriesDroppedWiring(): void
    {
        $pipeline = new MutationPipeline(
            $this->serializerDecoding(new ContentElement('el-1', 'Sw:Block')),
            $this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), [])),
        );

        $result = $pipeline->run($this->mutation([new ContentElement('el-1', 'Sw:New')], ['el-1'], [], ['legacy']), [['id' => 'el-1', 'component' => 'Sw:Block']], null);

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

        $pipeline = new MutationPipeline($this->serializerDecoding(new ContentElement('el-1', 'Sw:Block')), $diagnostics);

        $pipeline->run($this->mutation([$mutated], ['new-1']), [['id' => 'el-1', 'component' => 'Sw:Block']], $rootContext, $context);
    }

    #[TestDox('rejects a structurally invalid layout element with a 400')]
    public function testRunRejectsStructurallyInvalidLayout(): void
    {
        $pipeline = new MutationPipeline(
            static::createStub(ContentElementFieldSerializer::class),
            $this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), [])),
        );

        try {
            $pipeline->run($this->mutation([], []), [['component' => 'Sw:Block']], null);
            static::fail('Expected a ContentSystemException for the structurally invalid layout.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
        }
    }

    #[TestDox('rejects a nested client-defect decode failure as a 400 instead of letting it surface as a 500')]
    public function testRunRejectsClientDefectDecodeWith400(): void
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willThrowException(ContentSystemException::unknownLoaderEntity('prodct'));

        $pipeline = new MutationPipeline($serializer, $this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), [])));

        try {
            $pipeline->run($this->mutation([], []), [['id' => 'el-1', 'component' => 'Sw:Block']], null);
            static::fail('Expected a ContentSystemException for the client-defect decode failure.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
        }
    }

    #[TestDox('rethrows a non-client-defect decode fault unchanged')]
    public function testRunRethrowsInternalDecodeFault(): void
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willThrowException(ContentSystemException::layoutNotFound('x'));

        $pipeline = new MutationPipeline($serializer, $this->diagnosticsReturning(new LayoutAnalysis(new DiagnosticsReport([]), [])));

        $this->expectExceptionObject(ContentSystemException::layoutNotFound('x'));
        $pipeline->run($this->mutation([], []), [['id' => 'el-1', 'component' => 'Sw:Block']], null);
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

    private function serializerDecoding(ContentElement $element): ContentElementFieldSerializer
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willReturn($element);

        return $serializer;
    }

    private function diagnosticsReturning(LayoutAnalysis $analysis): LayoutDiagnostics
    {
        $diagnostics = static::createStub(LayoutDiagnostics::class);
        $diagnostics->method('analyze')->willReturn($analysis);

        return $diagnostics;
    }
}
