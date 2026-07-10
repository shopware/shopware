<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
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
 * When $bindingSpecificationId is also given, the named specification's wiring is then applied on top,
 * atomically after scaffold, via {@see BindingApplicator::apply()} (overwrite), so shared keys belong to the
 * explicit choice.
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

    public function apply(array $tree): array
    {
        $this->requireRegistered($this->registry, $this->type);

        $bindingSpecificationId = $this->bindingSpecificationId;

        $element = $bindingSpecificationId === null
            ? $this->scaffoldWithDefault($this->type)
            : $this->scaffoldBoundElement($bindingSpecificationId);

        $this->affected = [$element->getId()];

        if ($this->parentElementId === null) {
            return $this->insertAtRoot($tree, $this->index, [$element]);
        }

        $slot = $this->slot;

        if ($slot === null) {
            throw ContentSystemException::mutationSlotRequired();
        }

        if ($this->findNode($tree, $this->parentElementId) === null) {
            throw ContentSystemException::mutationTargetNotFound($this->parentElementId);
        }

        return $this->insertIntoSlot($tree, $this->parentElementId, $slot, $this->index, [$element]);
    }

    private function scaffoldBoundElement(string $bindingSpecificationId): ContentElement
    {
        $specification = $this->bindingRegistry->get($bindingSpecificationId);

        if ($specification === null) {
            throw ContentSystemException::bindingSpecificationNotFound($bindingSpecificationId);
        }

        if ($specification->type() !== $this->type) {
            throw ContentSystemException::bindingTypeMismatch($bindingSpecificationId, $specification->type(), $this->type);
        }

        // Default underneath, explicit on top: the default is fill-applied first, then the explicit specification
        // overwrites its shared keys, so an explicit choice always wins over the type's default wiring.
        return $this->bindingApplicator->apply($this->scaffoldWithDefault($this->type), $specification, $bindingSpecificationId);
    }

    /**
     * Scaffolds a fresh element of $type and fill-applies its default binding specification (resolved via
     * {@see AbstractLayoutMutation::resolveDefaultSpecification()}), attributed to the default's own qualified id.
     */
    private function scaffoldWithDefault(string $type): ContentElement
    {
        $element = $this->scaffoldElement($this->registry, $type);
        $default = $this->resolveDefaultSpecification($this->bindingRegistry, $type);

        if ($default === null) {
            return $element;
        }

        return $this->bindingApplicator->applyFillOnly($element, $default, $default->qualifiedId());
    }
}
