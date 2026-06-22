<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Splices an externally supplied element subtree into $parentElementId's $slot at $index (root when no parent is
 * given), reminting every id so a detached subtree (e.g. a replace's orphans) or a copied subtree can be re-placed
 * without collision. The inverse of the detachment a replace reports through orphaned(): nothing is created from a
 * type, the caller's own subtree is placed. Clients never supply ids; the minted ids come back in affected().
 *
 * @internal
 */
#[Package('framework')]
final class AttachElement extends AbstractLayoutMutation
{
    public function __construct(
        private readonly ContentElement $element,
        private readonly ?string $parentElementId = null,
        private readonly ?string $slot = null,
        private readonly ?int $index = null,
    ) {
    }

    public function apply(array $tree): array
    {
        $clone = $this->cloneWithNewIds($this->element);
        $this->affected = $this->subtreeIds($clone);

        if ($this->parentElementId === null) {
            return $this->insertAtRoot($tree, $this->index, [$clone]);
        }

        $slot = $this->slot;

        if ($slot === null) {
            throw ContentSystemException::mutationSlotRequired();
        }

        if ($this->findNode($tree, $this->parentElementId) === null) {
            throw ContentSystemException::mutationTargetNotFound($this->parentElementId);
        }

        return $this->insertIntoSlot($tree, $this->parentElementId, $slot, $this->index, [$clone]);
    }
}
