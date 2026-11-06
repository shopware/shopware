<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\Log\Package;

/**
 * The slot coordinates of a non-root element: the id of its parent and the slot it lives in.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ParentSlot
{
    public function __construct(
        public string $parentId,
        public string $slot,
    ) {
    }
}
