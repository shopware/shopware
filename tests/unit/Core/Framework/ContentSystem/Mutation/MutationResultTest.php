<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationResult;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MutationResult::class)]
class MutationResultTest extends TestCase
{
    #[TestDox('narrows resolutions to the mutation\'s affected elements')]
    public function testFromAnalyzedMutationNarrowsResolutionsToAffectedElements(): void
    {
        $layout = new StoredTree([new StoredElement('el-1', 'Sw:Card')]);

        $affectedResolution = [new PropertyResolution('headline', PropertyKind::Primitive, false, 'string', 'hi')];
        $unaffectedResolution = [new PropertyResolution('headline', PropertyKind::Primitive, false, 'string', 'stale')];
        $report = new DiagnosticsReport([]);
        $analysis = new LayoutAnalysis($report, [
            'el-1' => $affectedResolution,
            'el-2' => $unaffectedResolution,
        ]);

        $orphan = new StoredElement('orphan-1', 'Sw:Block');
        $droppedValue = StoredValue::ofString('dropped-property-value');

        $mutation = static::createStub(LayoutMutation::class);
        $mutation->method('affected')->willReturn(['el-1']);
        $mutation->method('orphaned')->willReturn([$orphan]);
        $mutation->method('droppedWiring')->willReturn(['dropped-wiring-key']);
        $mutation->method('droppedProperties')->willReturn(['legacy-headline' => $droppedValue]);

        $result = MutationResult::fromAnalyzedMutation($layout, $analysis, $mutation);

        static::assertSame(['el-1' => $affectedResolution], $result->resolutions);
        static::assertSame(['el-1'], $result->affectedElementIds);
        static::assertSame([$orphan], $result->orphaned);
        static::assertSame(['dropped-wiring-key'], $result->droppedWiring);
        static::assertSame(['legacy-headline' => $droppedValue], $result->droppedProperties);
        static::assertSame($layout, $result->layout);
        static::assertSame($report, $result->diagnostics);
    }
}
