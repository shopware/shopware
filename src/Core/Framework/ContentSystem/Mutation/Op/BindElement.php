<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Applies a registered binding specification's wiring onto one element via {@see BindingApplicator}, keeping the same
 * element id.
 *
 * @internal
 */
#[Package('framework')]
final class BindElement extends AbstractLayoutMutation
{
    public function __construct(
        private readonly AbstractContentSystemBindingSpecificationRegistry $registry,
        private readonly string $bindingSpecificationId,
        private readonly string $elementId,
        private readonly BindingApplicator $applicator,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $specification = $this->registry->get($this->bindingSpecificationId);

        if ($specification === null) {
            throw ContentSystemException::bindingSpecificationNotFound($this->bindingSpecificationId);
        }

        $node = $tree->find($this->elementId);

        if ($node === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        if ($specification->type() !== $node->component) {
            throw ContentSystemException::bindingTypeMismatch($this->bindingSpecificationId, $specification->type(), $node->component);
        }

        $replacement = $this->applicator->apply($node, $specification, $this->bindingSpecificationId);

        $result = $tree->replace($this->elementId, $replacement);

        $this->affected = [$replacement->id];

        return $result;
    }
}
