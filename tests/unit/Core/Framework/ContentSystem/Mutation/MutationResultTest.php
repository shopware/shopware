<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
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
    #[TestDox('lands every fromParts argument on the field its position names')]
    public function testFromPartsLandsEveryArgumentOnItsOwnField(): void
    {
        $layout = new StoredTree([new StoredElement('el-1', 'Sw:Card')]);
        $resolutions = ['el-1' => [new PropertyResolution('headline', PropertyKind::Primitive, false, 'string', 'hi')]];
        $diagnostics = new DiagnosticsReport([]);
        $orphan = new StoredElement('orphan-1', 'Sw:Block');
        $droppedValue = StoredValue::ofString('Old headline');

        $result = MutationResult::fromParts(
            $layout,
            $resolutions,
            $diagnostics,
            ['el-1'],
            [$orphan],
            ['wiring-legacy'],
            ['headline' => $droppedValue],
        );

        static::assertSame($layout, $result->layout);
        static::assertSame($resolutions, $result->resolutions);
        static::assertSame($diagnostics, $result->diagnostics);
        static::assertSame(['el-1'], $result->affectedElementIds);
        static::assertSame([$orphan], $result->orphaned);
        static::assertSame(['wiring-legacy'], $result->droppedWiring);
        static::assertSame(['headline' => $droppedValue], $result->droppedProperties);
    }

    #[TestDox('defaults orphaned, dropped wiring and dropped properties to empty arrays')]
    public function testFromPartsDefaultsTheOptionalFieldsToEmptyArrays(): void
    {
        $result = MutationResult::fromParts(new StoredTree([]), [], new DiagnosticsReport([]), []);

        static::assertSame([], $result->orphaned);
        static::assertSame([], $result->droppedWiring);
        static::assertSame([], $result->droppedProperties);
    }
}
