<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\Log\Package;

/**
 * Where an element sits in a tree: the node itself, its index within its containing list, and its parent slot
 * coordinates. $parent is null for a root element (then $index is the index in the root list).
 *
 * Built from {@see StoredTree::locate()}'s array by
 * {@see AbstractLayoutMutation::locate()}; that array stays the single source and this is a typed view of it.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ElementLocation
{
    public function __construct(
        public StoredElement $node,
        public int $index,
        public ?ParentSlot $parent = null,
    ) {
    }
}
