<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Replaces $containerElementId with its slot children, hoisted into the container's parent slot at the
 * container's position. The children flatten across all the container's slots in slot order. Reports the whole
 * hoisted forest as affected.
 *
 * @internal
 */
#[Package('framework')]
final class UnwrapElement extends AbstractLayoutMutation
{
    public function __construct(
        private readonly string $containerElementId,
    ) {
    }

    public function apply(array $tree): array
    {
        $location = $this->locate($tree, $this->containerElementId);

        if ($location === null) {
            throw ContentSystemException::mutationTargetNotFound($this->containerElementId);
        }

        $children = $this->childList($location->node);
        $this->affected = array_merge([], ...array_map($this->subtreeIds(...), $children));

        $without = $this->removeSubtree($tree, $this->containerElementId);

        if ($location->parent === null) {
            return $this->insertAtRoot($without, $location->index, $children);
        }

        return $this->insertIntoSlot($without, $location->parent->parentId, $location->parent->slot, $location->index, $children);
    }
}
