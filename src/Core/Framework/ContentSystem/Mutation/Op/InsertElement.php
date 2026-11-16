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
 * When $bindingSpecificationId is also given, the named specification is applied first via
 * {@see BindingApplicator::apply()} and the default is fill-applied after it, atomically after scaffold, so the
 * default sits underneath and shared keys (wiring, attribution and input defaults) belong to the explicit choice.
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
     * Scaffolds a fresh element of the type, applies the named binding specification onto it when one is given,
     * then fill-applies the type's default binding specification (resolved via
     * {@see AbstractLayoutMutation::resolveDefaultSpecification()}), attributed to the default's own qualified id.
     * The scaffold's id is minted per request and never reaches the caller, so a `bindingRootSourceNotScoped`
     * raised here names no element, like the `bindingTypeMismatch` of a named specification of another type.
     */
    private function scaffold(?string $rootSource): StoredElement
    {
        $bindingSpecificationId = $this->bindingSpecificationId;

        if ($bindingSpecificationId === null) {
            return $this->applyDefaultBinding($this->bindingRegistry, $this->bindingApplicator, $this->scaffoldElement($this->registry, $this->type), null, $rootSource);
        }

        $specification = $this->namedSpecification($bindingSpecificationId);
        $element = $this->bindingApplicator->apply($this->scaffoldElement($this->registry, $this->type), $specification, $bindingSpecificationId, null, $rootSource);

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
