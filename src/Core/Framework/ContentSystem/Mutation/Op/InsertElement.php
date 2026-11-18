<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Inserts a fresh element of $type (with primitive defaults seeded from its type, no context/loader wiring)
 * into $parentElementId's $slot at $index, or appended to the root when no parent is given. Subsumes the
 * standalone scaffold action: the pipeline's diagnostics pass reports the new element's auto-wiring and
 * candidate sources.
 *
 * The type's default binding specification, when it has exactly one, is fill-applied onto the fresh element via
 * {@see BindingApplicator::applyFillOnly()} before insertion (zero defaults is a no-op; more than one throws).
 * When $bindingSpecificationId is also given, the named specification is applied first and the default fills after
 * it, so shared keys belong to the explicit choice.
 * A default key the named specification wires is never resolved against the root source.
 *
 * @internal
 */
#[Package('framework')]
final class InsertElement extends AbstractLayoutMutation
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly string $type,
        private readonly AbstractContentSystemBindingSpecificationRegistry $bindingRegistry,
        private readonly BindingApplicator $bindingApplicator,
        private readonly ?string $bindingSpecificationId = null,
        private readonly ?string $parentElementId = null,
        private readonly ?int $index = null,
        private readonly ?string $slot = null,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $this->requireRegistered($this->registry, $this->type);

        $element = $this->scaffold($tree->rootSource);

        $this->affected = [$element->id];
        $this->created = [$element->id];
        $this->freshlyScaffolded = [$element->id];

        if ($this->parentElementId === null) {
            return $tree->insertAtRoot($this->index, [$element]);
        }

        $slot = $this->slot;

        if ($slot === null) {
            throw ContentSystemException::mutationSlotRequired();
        }

        if ($tree->find($this->parentElementId) === null) {
            throw ContentSystemException::mutationTargetNotFound($this->parentElementId);
        }

        return $tree->insertIntoSlot($this->parentElementId, $slot, $this->index, [$element]);
    }

    /**
     * The default is resolved via {@see AbstractLayoutMutation::resolveDefaultSpecification()} and attributed to its
     * own qualified id.
     */
    private function scaffold(?string $rootSource): StoredElement
    {
        $bindingSpecificationId = $this->bindingSpecificationId;
        $specification = $bindingSpecificationId !== null ? $this->namedSpecification($bindingSpecificationId) : null;
        $element = $this->scaffoldElement($this->registry, $this->type);

        if ($bindingSpecificationId !== null && $specification !== null) {
            $element = $this->bindingApplicator->apply($element, $specification, $bindingSpecificationId, null, $rootSource);
        }

        return $this->applyDefaultBinding($this->bindingRegistry, $this->bindingApplicator, $element, null, $rootSource);
    }

    private function namedSpecification(string $bindingSpecificationId): BindingSpecification
    {
        $specification = $this->bindingRegistry->get($bindingSpecificationId);

        if ($specification === null) {
            throw ContentSystemException::bindingSpecificationNotFound($bindingSpecificationId);
        }

        if ($specification->type() !== $this->type) {
            throw ContentSystemException::bindingTypeMismatch($bindingSpecificationId, $specification->type(), $this->type, null);
        }

        return $specification;
    }
}
