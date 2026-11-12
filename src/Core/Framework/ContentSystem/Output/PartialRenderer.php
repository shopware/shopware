<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Handles partial rendering by pruning and extracting target elements.
 *
 * The two halves sit on either side of the lowering: `pruneToTarget()` runs while the tree is still the
 * storage model, `extractTarget()` after the render step on a {@see RenderedElement} tree.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class PartialRenderer
{
    public function __construct(
        private readonly ElementTreePruner $treePruner,
        private readonly ContextDependencyAnalyzer $dependencyAnalyzer,
        private readonly SubTreeExtractor $subTreeExtractor
    ) {
    }

    /**
     * Prunes element trees to target element while preserving context-dependent ancestors.
     *
     * Pre-hydration pruning keeps context dependencies to ensure data flows correctly
     * during hydration. Post-hydration extraction removes these ancestors.
     *
     * A target that is in no root at all leaves an empty forest here; `extractTarget()` is where that
     * turns into the `elementNotFound` the caller sees.
     *
     * @param list<StoredElement> $elements
     *
     * @return list<StoredElement> Pruned elements containing target
     */
    public function pruneToTarget(array $elements, string $targetElementId): array
    {
        $prunedElements = [];

        // Try each root element - target may be in any root
        foreach ($elements as $element) {
            $pruned = $this->treePruner->pruneToPathAndDescendants(
                $element,
                $targetElementId,
                $this->dependencyAnalyzer
            );

            if ($pruned === null) {
                continue;
            }

            $prunedElements[] = $pruned;
        }

        return $prunedElements;
    }

    /**
     * Extracts target element from pruned trees.
     *
     * Post-hydration extraction removes context-dependent ancestors that were kept
     * during pruning, returning only the target element and its descendants.
     *
     * @param list<RenderedElement> $elements
     *
     * @throws ContentSystemException If target element not found in any element
     */
    public function extractTarget(array $elements, string $targetElementId): RenderedElement
    {
        foreach ($elements as $element) {
            $targetElement = $this->subTreeExtractor->extract($element, $targetElementId);
            if ($targetElement !== null) {
                return $targetElement;
            }
        }

        throw ContentSystemException::elementNotFound($targetElementId);
    }
}
