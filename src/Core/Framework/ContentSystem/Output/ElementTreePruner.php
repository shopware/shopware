<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\Log\Package;

/**
 * Prunes a content-element tree to a target element's path plus its descendants: the pre-hydration optimization for
 * partial rendering, which drops the siblings along the path because context flows parent to child only.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ElementTreePruner
{
    /**
     * @return list<string> Element IDs from root to target (inclusive), empty array if not found
     */
    public function findPathToElement(ContentElement $root, string $targetId): array
    {
        $visitor = new PathFinderVisitor($targetId);
        $root->traverse($visitor);

        return $visitor->getPath();
    }

    /**
     * Pre-hydration optimization: discards siblings at each level since context flows
     * parent→child only, not between siblings.
     */
    public function pruneToPathAndDescendants(
        ContentElement $root,
        string $targetId,
        ContextDependencyAnalyzer $dependencyAnalyzer
    ): ContentElement {
        $resolved = $this->resolvePath($root, $targetId);

        if ($resolved === null) {
            throw ContentSystemException::elementNotFound($targetId);
        }

        [$pathElements, $slotNames] = $resolved;
        $dataRootIndex = $dependencyAnalyzer->findDataRootIndex($pathElements);

        return $this->reconstructPrunedTree(
            $pathElements,
            $slotNames,
            $dataRootIndex,
            \count($pathElements) - 1
        );
    }

    /**
     * Finds the target and returns the elements from `$element` down to it, recording for each of them
     * the slot that holds the next one.
     *
     * The elements and their slots come out of one descent, so the reconstruction can never disagree
     * with the search about which child a step meant: ids are supposed to be unique across the forest,
     * but a raw-SQL or migration write can put the same id in two slots, and re-locating a step by id
     * would then be free to pick the sibling the search did not walk through.
     *
     * Pre-order, first match wins, slot order — the order {@see findPathToElement} reports.
     *
     * @return array{list<ContentElement>, list<string>}|null The path elements and, per element, the
     *                                                        slot holding its successor; null when the
     *                                                        target is not in this tree
     */
    private function resolvePath(ContentElement $element, string $targetId): ?array
    {
        if ($element->getId() === $targetId) {
            return [[$element], []];
        }

        foreach ($element->getSlots() as $slotName => $slotContent) {
            foreach ($slotContent as $child) {
                $resolved = $this->resolvePath($child, $targetId);

                if ($resolved === null) {
                    continue;
                }

                [$elements, $slotNames] = $resolved;

                return [[$element, ...$elements], [$slotName, ...$slotNames]];
            }
        }

        return null;
    }

    /**
     * @param list<ContentElement> $pathElements
     * @param list<string> $slotNames
     */
    private function reconstructPrunedTree(
        array $pathElements,
        array $slotNames,
        int $startIndex,
        int $targetIndex
    ): ContentElement {
        if ($startIndex === $targetIndex) {
            return clone $pathElements[$targetIndex];
        }

        // Build from bottom up (target to context root) to handle immutability
        return $this->reconstructFromBottom($pathElements, $slotNames, $startIndex, $targetIndex);
    }

    /**
     * @param list<ContentElement> $pathElements
     * @param list<string> $slotNames
     */
    private function reconstructFromBottom(
        array $pathElements,
        array $slotNames,
        int $currentIndex,
        int $targetIndex
    ): ContentElement {
        if ($currentIndex === $targetIndex) {
            return clone $pathElements[$targetIndex];
        }

        $child = $this->reconstructFromBottom($pathElements, $slotNames, $currentIndex + 1, $targetIndex);

        $currentElement = $pathElements[$currentIndex];

        return new ContentElement(
            $currentElement->getId(),
            $currentElement->getComponent(),
            $currentElement->getDataRequirements(),
            $currentElement->getProperties(),
            [$slotNames[$currentIndex] => new SlotContent([$child])],
            new ContextDefinitions(
                $currentElement->getProvidesContext(),
                $currentElement->getAcceptsContext()
            ),
            $currentElement->getStyle(),
        );
    }
}
