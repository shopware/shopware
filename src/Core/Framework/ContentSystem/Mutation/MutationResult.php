<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
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
    public function __construct(
        public StoredTree $layout,
        public array $resolutions,
        public DiagnosticsReport $diagnostics,
        public array $affectedElementIds,
        public array $orphaned = [],
        public array $droppedWiring = [],
        public array $droppedProperties = [],
    ) {
    }
}
