<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Splices an externally supplied element subtree into $parentElementId's $slot at $index (root when no parent is
 * given), reminting every id so a detached subtree (e.g. a replace's orphans) or a copied subtree can be re-placed
 * without collision. The inverse of the detachment a replace reports through orphaned(): nothing is created from a
 * type, the caller's own subtree is placed. The supplied root's component must be a registered element type
 * (mutationUnknownType), matching the type check insert/replace/wrap run. Clients never supply ids; the minted
 * ids come back in affected().
 *
 * @internal
 */
#[Package('framework')]
final class AttachElement extends AbstractLayoutMutation
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly StoredElement $element,
        private readonly ?string $parentElementId = null,
        private readonly ?string $slot = null,
        private readonly ?int $index = null,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $this->requireRegistered($this->registry, $this->element->component);

        $clone = $this->cloneWithNewIds($this->element);
        $this->affected = $this->subtreeIds($clone);

        if ($this->parentElementId === null) {
            return $tree->insertAtRoot($this->index, [$clone]);
        }

        $slot = $this->slot;

        if ($slot === null) {
            throw ContentSystemException::mutationSlotRequired();
        }

        if ($tree->find($this->parentElementId) === null) {
            throw ContentSystemException::mutationTargetNotFound($this->parentElementId);
        }

        return $tree->insertIntoSlot($this->parentElementId, $slot, $this->index, [$clone]);
    }
}
