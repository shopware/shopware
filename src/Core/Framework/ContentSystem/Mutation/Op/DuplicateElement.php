<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Deep-clones $elementId's subtree, reminting every node id, and splices the clone as the next sibling (or at
 * $index). Context wiring is key/position-based, never id-based, so it carries over unchanged with no internal
 * id references to rewrite.
 *
 * @internal
 */
#[Package('framework')]
final class DuplicateElement extends AbstractLayoutMutation
{
    public function __construct(
        private readonly string $elementId,
        private readonly ?int $index = null,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $location = $this->locate($tree, $this->elementId);

        if ($location === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        $clone = $this->cloneWithNewIds($location->node);
        $this->affected = $this->subtreeIds($clone);

        $index = $this->index ?? $location->index + 1;

        if ($location->parent === null) {
            return $tree->insertAtRoot($index, [$clone]);
        }

        return $tree->insertIntoSlot($location->parent->parentId, $location->parent->slot, $index, [$clone]);
    }
}
