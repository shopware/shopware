<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Relocates $elementId and its subtree under $newParentId's $newSlot at $index, or to the root when no parent
 * is given. A move onto the element itself or one of its descendants is rejected as a cycle. Affects the moved
 * subtree only when the parent changes; a same-parent reorder or slot change affects nothing.
 *
 * @internal
 */
#[Package('framework')]
final class MoveElement extends AbstractLayoutMutation
{
    public function __construct(
        private readonly string $elementId,
        private readonly ?string $newParentId = null,
        private readonly ?string $newSlot = null,
        private readonly ?int $index = null,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $location = $this->locate($tree, $this->elementId);

        if ($location === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        $node = $location->node;

        if ($this->newParentId !== null) {
            if (\in_array($this->newParentId, $this->subtreeIds($node), true)) {
                throw ContentSystemException::mutationCycle($this->elementId);
            }

            if ($tree->find($this->newParentId) === null) {
                throw ContentSystemException::mutationTargetNotFound($this->newParentId);
            }
        }

        $oldParentId = $location->parent?->parentId;

        $this->affected = $this->newParentId === $oldParentId ? [] : $this->subtreeIds($node);

        if ($this->newParentId === null) {
            return $tree->remove($this->elementId)->insertAtRoot($this->index, [$node]);
        }

        $slot = $this->newSlot;

        if ($slot === null && $this->newParentId === $oldParentId) {
            $slot = $location->parent->slot;
        }

        if ($slot === null) {
            throw ContentSystemException::mutationSlotRequired();
        }

        return $tree->remove($this->elementId)->insertIntoSlot($this->newParentId, $slot, $this->index, [$node]);
    }
}
