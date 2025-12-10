<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Output;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Content\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\Log\Package;

/**
 * Tree manipulation utilities using visitor pattern for state tracking and direct recursion for early-exit/reconstruction.
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ElementTreeUtil
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
        $path = $this->findPathToElement($root, $targetId);

        if ($path === []) {
            throw ContentSystemException::elementNotFound($targetId);
        }

        $pathElements = $this->buildPathElements($root, $path);
        $dataRootIndex = $dependencyAnalyzer->findDataRootIndex($pathElements);

        return $this->reconstructPrunedTree(
            $pathElements,
            $dataRootIndex,
            \count($pathElements) - 1
        );
    }

    /**
     * @param list<string> $path Element IDs from root to target
     *
     * @return list<ContentElement>
     */
    private function buildPathElements(ContentElement $root, array $path): array
    {
        $elements = [$root];
        $current = $root;
        $pathCount = \count($path);

        for ($i = 1; $i < $pathCount; ++$i) {
            $nextId = $path[$i];

            // Search only among direct children (O(children) instead of O(tree))
            $found = $this->findDirectChild($current, $nextId);

            if ($found === null) {
                throw ContentSystemException::pathIntegrityViolation(
                    "Element {$nextId} not found as direct child of {$current->getId()}"
                );
            }

            $elements[] = $found;
            $current = $found;
        }

        return $elements;
    }

    /**
     * @param array<ContentElement> $pathElements
     */
    private function reconstructPrunedTree(
        array $pathElements,
        int $startIndex,
        int $targetIndex
    ): ContentElement {
        if ($startIndex === $targetIndex) {
            return clone $pathElements[$targetIndex];
        }

        // Build from bottom up (target to context root) to handle immutability
        return $this->reconstructFromBottom($pathElements, $startIndex, $targetIndex);
    }

    /**
     * @param array<ContentElement> $pathElements
     */
    private function reconstructFromBottom(
        array $pathElements,
        int $currentIndex,
        int $targetIndex
    ): ContentElement {
        if ($currentIndex === $targetIndex) {
            return clone $pathElements[$targetIndex];
        }

        $child = $this->reconstructFromBottom($pathElements, $currentIndex + 1, $targetIndex);

        $currentElement = $pathElements[$currentIndex];
        $nextElement = $pathElements[$currentIndex + 1];

        $slotName = $this->findSlotContaining($currentElement, $nextElement->getId());

        if ($slotName === null) {
            throw ContentSystemException::pathIntegrityViolation(
                "Element {$nextElement->getId()} not found in any slot of parent {$currentElement->getId()}"
            );
        }

        return new ContentElement(
            $currentElement->getId(),
            $currentElement->getComponent(),
            $currentElement->getDataRequirements(),
            $currentElement->getProperties(),
            [$slotName => new SlotContent([$child])],
            new ContextDefinitions(
                $currentElement->getProvidesContext(),
                $currentElement->getAcceptsContext()
            )
        );
    }

    private function findSlotContaining(ContentElement $parent, string $childId): ?string
    {
        foreach ($parent->getSlots() as $slotName => $slotContent) {
            foreach ($slotContent as $element) {
                if ($element->getId() === $childId) {
                    return $slotName;
                }
            }
        }

        return null;
    }

    private function findDirectChild(ContentElement $parent, string $childId): ?ContentElement
    {
        foreach ($parent->allSlotElements() as $child) {
            if ($child->getId() === $childId) {
                return $child;
            }
        }

        return null;
    }
}
