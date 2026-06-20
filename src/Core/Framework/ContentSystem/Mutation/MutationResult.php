<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\Log\Package;

/**
 * The outcome of one mutation: the re-resolved layout, the per-affected-element resolutions, the
 * diagnostics report, the affected element ids, and any subtrees the op detached.
 *
 * @internal
 */
#[Package('framework')]
final readonly class MutationResult
{
    /**
     * @param list<ContentElement> $layout
     * @param array<string, list<PropertyResolution>> $resolutions keyed by element id
     * @param list<string> $affectedElementIds
     * @param list<ContentElement> $orphaned subtrees detached by the op, returned so the caller can re-place them
     * @param list<string> $droppedWiring wiring keys the op dropped, reported so the caller can re-wire
     */
    public function __construct(
        public array $layout,
        public array $resolutions,
        public DiagnosticsReport $diagnostics,
        public array $affectedElementIds,
        public array $orphaned = [],
        public array $droppedWiring = [],
    ) {
    }
}
