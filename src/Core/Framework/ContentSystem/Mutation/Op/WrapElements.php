<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\ContentSystem\Mutation\ElementLocation;
use Shopware\Core\Framework\ContentSystem\Mutation\ParentSlot;
use Shopware\Core\Framework\Log\Package;

/**
 * Mints a $containerType element, moves $elementIds (which must be siblings in one slot, or all roots) into
 * its $slot in their original order, and places the container where the first target was.
 *
 * @internal
 */
#[Package('framework')]
final class WrapElements extends AbstractLayoutMutation
{
    /**
     * @param list<string> $elementIds
     */
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly array $elementIds,
        private readonly string $containerType,
        private readonly ?string $slot = null,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $this->requireRegistered($this->registry, $this->containerType);

        $slot = $this->slot;

        if ($slot === null) {
            throw ContentSystemException::mutationSlotRequired();
        }

        $locations = $this->locateTargets($tree);

        $this->requireSiblings($locations);

        $ordered = $this->orderByIndex($locations);
        $position = $this->firstPosition($locations);

        $containerElement = $this->scaffoldElement($this->registry, $this->containerType, [$slot => $ordered]);
        $this->affected = [$containerElement->id, ...$this->elementIds];

        $without = $tree;
        foreach ($this->elementIds as $id) {
            $without = $without->remove($id);
        }

        if ($locations[0]->parent === null) {
            return $without->insertAtRoot($position, [$containerElement]);
        }

        return $without->insertIntoSlot($locations[0]->parent->parentId, $locations[0]->parent->slot, $position, [$containerElement]);
    }

    /**
     * @return non-empty-list<ElementLocation>
     */
    private function locateTargets(StoredTree $tree): array
    {
        if ($this->elementIds === []) {
            throw ContentSystemException::mutationInvalidWrapTargets('at least one element is required');
        }

        if (\count(array_unique($this->elementIds)) !== \count($this->elementIds)) {
            throw ContentSystemException::mutationInvalidWrapTargets('they must be distinct');
        }

        $locations = [];

        foreach ($this->elementIds as $id) {
            $location = $this->locate($tree, $id);

            if ($location === null) {
                throw ContentSystemException::mutationTargetNotFound($id);
            }

            $locations[] = $location;
        }

        return $locations;
    }

    /**
     * @param list<ElementLocation> $locations
     */
    private function requireSiblings(array $locations): void
    {
        $first = $locations[0]->parent;

        foreach ($locations as $location) {
            if (!$this->sameContainer($first, $location->parent)) {
                throw ContentSystemException::mutationInvalidWrapTargets('they must be siblings in one slot');
            }
        }
    }

    private function sameContainer(?ParentSlot $a, ?ParentSlot $b): bool
    {
        if ($a === null || $b === null) {
            return $a === null && $b === null;
        }

        return $a->parentId === $b->parentId && $a->slot === $b->slot;
    }

    /**
     * @param list<ElementLocation> $locations
     *
     * @return list<StoredElement>
     */
    private function orderByIndex(array $locations): array
    {
        usort($locations, static fn (ElementLocation $a, ElementLocation $b): int => $a->index <=> $b->index);

        return array_values(array_map(static fn (ElementLocation $location): StoredElement => $location->node, $locations));
    }

    /**
     * @param non-empty-list<ElementLocation> $locations
     */
    private function firstPosition(array $locations): int
    {
        return min(array_map(static fn (ElementLocation $location): int => $location->index, $locations));
    }
}
