<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
final class InsertPreset extends AbstractLayoutMutation
{
    /**
     * @param list<StoredElement> $elements
     */
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly array $elements,
        private readonly ?string $parentElementId = null,
        private readonly ?string $slot = null,
        private readonly ?int $index = null,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $clones = [];

        foreach ($this->elements as $element) {
            $this->requireRegistered($this->registry, $element->component);

            $clone = $this->cloneWithNewIds($element);
            $clones[] = $clone;
            $this->affected = array_merge($this->affected, $this->subtreeIds($clone));
        }

        if ($this->parentElementId === null) {
            return $tree->insertAtRoot($this->index, $clones);
        }

        if ($this->slot === null) {
            throw ContentSystemException::mutationSlotRequired();
        }

        if ($tree->find($this->parentElementId) === null) {
            throw ContentSystemException::mutationTargetNotFound($this->parentElementId);
        }

        return $tree->insertIntoSlot($this->parentElementId, $this->slot, $this->index, $clones);
    }
}
