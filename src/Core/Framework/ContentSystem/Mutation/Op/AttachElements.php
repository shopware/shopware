<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class AttachElements extends AbstractLayoutMutation
{
    /**
     * @param list<StoredElement> $elements
     */
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly array $elements,
        private readonly AbstractContentSystemBindingSpecificationRegistry $bindingRegistry,
        private readonly BindingApplicator $bindingApplicator,
        private readonly ?string $parentElementId = null,
        private readonly ?string $slot = null,
        private readonly ?int $index = null,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        foreach ($this->elements as $offset => $element) {
            $attach = new AttachElement(
                $this->registry,
                $element,
                $this->bindingRegistry,
                $this->bindingApplicator,
                $this->parentElementId,
                $this->slot,
                $this->index === null ? null : $this->index + $offset,
            );

            $tree = $attach->apply($tree);
            $this->affected = array_merge($this->affected, $attach->affected());
        }

        $this->created = $this->affected;

        return $tree;
    }
}
