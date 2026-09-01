<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Prunes a stored element tree to a target element's path plus its descendants: the pre-render optimization for
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
     * Pre-hydration optimization: discards siblings at each level since context flows
     * parent→child only, not between siblings.
     *
     * A target that is not in this root is not an error: the forest has other roots to try, and the
     * caller decides what an exhausted forest means. `null` is that answer.
     */
    public function pruneToPathAndDescendants(
        StoredElement $root,
        string $targetId,
        ContextDependencyAnalyzer $dependencyAnalyzer
    ): ?StoredElement {
        $resolved = $this->resolvePath($root, $targetId);

        if ($resolved === null) {
            return null;
        }

        [$pathElements, $slotNames] = $resolved;

        return $this->reconstructFromBottom(
            $pathElements,
            $slotNames,
            $dependencyAnalyzer->findDataRootIndex($pathElements),
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
     * Pre-order, first match wins, slot order.
     *
     * @return array{list<StoredElement>, list<string>}|null The path elements and, per element, the
     *                                                       slot holding its successor; null when the
     *                                                       target is not in this tree
     */
    private function resolvePath(StoredElement $element, string $targetId): ?array
    {
        if ($element->id === $targetId) {
            return [[$element], []];
        }

        foreach ($element->slots as $slotName => $children) {
            foreach ($children as $child) {
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
     * Rebuilds the kept path from the target upwards, each ancestor carrying only the one slot that leads
     * on. It rebuilds through {@see StoredElement::withSlots()} rather than through the constructor, the
     * same idiom `Layout/StoredTree`'s surgery uses: a field added to the element later rides across on its
     * own, where a hand-written constructor call would drop it without anything failing.
     *
     * @param list<StoredElement> $pathElements
     * @param list<string> $slotNames
     */
    private function reconstructFromBottom(
        array $pathElements,
        array $slotNames,
        int $currentIndex,
        int $targetIndex
    ): StoredElement {
        if ($currentIndex === $targetIndex) {
            return $pathElements[$targetIndex];
        }

        $child = $this->reconstructFromBottom($pathElements, $slotNames, $currentIndex + 1, $targetIndex);

        return $pathElements[$currentIndex]->withSlots([$slotNames[$currentIndex] => [$child]]);
    }
}
