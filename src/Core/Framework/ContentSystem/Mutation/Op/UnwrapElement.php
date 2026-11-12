<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Replaces $containerElementId with its slot children, hoisted into the container's parent slot at the
 * container's position. The children flatten across all the container's slots in slot order. Reports the whole
 * hoisted forest as affected, and the removed container's own static property values ({@see droppedProperties()})
 * and consumed wiring — its data requirements plus accepted context ({@see droppedWiring()}) — so neither is
 * silently lost. Context the container provided to its descendants is not reported here; a hoisted descendant
 * that depended on it surfaces as a BrokenRequiredChain binding violation in the diagnostics pass instead.
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

    public function apply(StoredTree $tree): StoredTree
    {
        $location = $this->locate($tree, $this->containerElementId);

        if ($location === null) {
            throw ContentSystemException::mutationTargetNotFound($this->containerElementId);
        }

        $children = $this->childList($location->node);
        $this->affected = array_merge([], ...array_map($this->subtreeIds(...), $children));

        // The container is removed but its hoisted children survive: its own static property values and the wiring
        // it consumed (data requirements + accepted context) have no home on any child, so report them rather than
        // drop them silently. Context the container *provided* is not reported here — its loss surfaces as a
        // BrokenRequiredChain binding violation in the diagnostics pass instead.
        $this->droppedProperties = $location->node->properties();
        $this->droppedWiring = array_values(array_unique([
            ...array_keys($location->node->dataRequirements),
            ...array_keys($location->node->contextDefinitions->getAllConsumers()),
        ]));

        $without = $tree->remove($this->containerElementId);

        if ($location->parent === null) {
            return $without->insertAtRoot($location->index, $children);
        }

        return $without->insertIntoSlot($location->parent->parentId, $location->parent->slot, $location->index, $children);
    }
}
