<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class MutationResult
{
    /**
     * @param array<string, list<PropertyResolution>> $resolutions keyed by element id
     * @param list<string> $affectedElementIds
     * @param list<StoredElement> $orphaned subtrees detached by the op, returned so the caller can re-place them
     * @param list<string> $droppedWiring wiring keys the op dropped, reported so the caller can re-wire
     * @param array<string, StoredValue> $droppedProperties static property values the op could not carry over, keyed by property key
     */
    private function __construct(
        public StoredTree $layout,
        public array $resolutions,
        public DiagnosticsReport $diagnostics,
        public array $affectedElementIds,
        public array $orphaned = [],
        public array $droppedWiring = [],
        public array $droppedProperties = [],
    ) {
    }

    /**
     * The single owner of the result assembly for a mutation that has been applied and diagnosed, so the rule
     * restricting the carried resolutions to the affected set is stated once for every runner.
     */
    public static function fromAnalyzedMutation(StoredTree $mutated, LayoutAnalysis $analysis, LayoutMutation $mutation): self
    {
        $affected = $mutation->affected();

        return new self(
            $mutated,
            array_intersect_key($analysis->resolutions, array_flip($affected)),
            $analysis->report,
            $affected,
            $mutation->orphaned(),
            $mutation->droppedWiring(),
            $mutation->droppedProperties(),
        );
    }

    /**
     * @param array<string, list<PropertyResolution>> $resolutions keyed by element id
     * @param list<string> $affectedElementIds
     * @param list<StoredElement> $orphaned
     * @param list<string> $droppedWiring
     * @param array<string, StoredValue> $droppedProperties keyed by property key
     */
    public static function fromParts(
        StoredTree $layout,
        array $resolutions,
        DiagnosticsReport $diagnostics,
        array $affectedElementIds,
        array $orphaned = [],
        array $droppedWiring = [],
        array $droppedProperties = [],
    ): self {
        return new self($layout, $resolutions, $diagnostics, $affectedElementIds, $orphaned, $droppedWiring, $droppedProperties);
    }
}
