<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Where an element sits in a tree: the node itself, its index within its containing list, and its parent slot
 * coordinates. $parent is null for a root element (then $index is the index in the root list).
 *
 * @internal
 */
#[Package('framework')]
final readonly class ElementLocation
{
    public function __construct(
        public ContentElement $node,
        public int $index,
        public ?ParentSlot $parent = null,
    ) {
    }
}
