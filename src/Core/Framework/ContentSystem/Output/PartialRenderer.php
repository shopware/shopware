<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\Log\Package;

/**
 * Handles partial rendering by pruning and extracting target elements.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class PartialRenderer
{
    public function __construct(
        private readonly ElementTreeUtil $treeUtil,
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
     * @param list<ContentElement> $elements
     *
     * @return list<ContentElement> Pruned elements containing target
     */
    public function pruneToTarget(array $elements, string $targetElementId): array
    {
        $prunedElements = [];

        // Try each root element - target may be in any root
        foreach ($elements as $element) {
            try {
                $prunedElement = $this->treeUtil->pruneToPathAndDescendants(
                    $element,
                    $targetElementId,
                    $this->dependencyAnalyzer
                );
                $prunedElements[] = $prunedElement;
            } catch (ContentSystemException) {
                // Element not found in this root, try next
            }
        }

        return $prunedElements;
    }

    /**
     * Extracts target element from pruned trees.
     *
     * Post-hydration extraction removes context-dependent ancestors that were kept
     * during pruning, returning only the target element and its descendants.
     *
     * @param array<ContentElement> $elements
     *
     * @throws ContentSystemException If target element not found in any element
     */
    public function extractTarget(array $elements, string $targetElementId): ContentElement
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
