<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Removes one property mapping without touching its authored fallback value.
 *
 * @internal
 */
#[Package('framework')]
final class UnmapProperty extends AbstractLayoutMutation
{
    public function __construct(
        private readonly string $elementId,
        private readonly string $propertyKey,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $node = $tree->find($this->elementId);

        if ($node === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        $definitions = $node->contextDefinitions;
        $consumers = $definitions->getAllConsumers();
        $consumer = $consumers[$this->propertyKey] ?? null;

        if ($consumer === null || $consumer->sourcePath === null) {
            return $tree;
        }

        unset($consumers[$this->propertyKey]);

        $replacement = $node->withContextDefinitions(new ContextDefinitions(
            $definitions->getAllProviders(),
            $consumers,
        ));
        $this->affected = [$replacement->id];

        return $tree->replace($this->elementId, $replacement);
    }
}
