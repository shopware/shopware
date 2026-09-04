<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Deletes $elementId and its whole subtree. Surviving elements' wiring is left untouched, and no
 * surviving element is affected.
 *
 * @internal
 */
#[Package('framework')]
final class RemoveElement extends AbstractLayoutMutation
{
    public function __construct(
        private readonly string $elementId,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        if ($tree->find($this->elementId) === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        return $tree->remove($this->elementId);
    }
}
