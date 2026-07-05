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
 * When $bindingSpecificationId is given, the named specification's wiring is applied onto the fresh element
 * atomically after scaffold via {@see BindingApplicator}, so the inserted element carries the binding's data
 * requirements, seeded input defaults, and attribution. The binding registry and applicator are then required
 * collaborators; supplying an id without them is a construction defect
 * ({@see ContentSystemException::mutationBindingCollaboratorsMissing()}, 500).
 *
 * The three binding arguments trail the placement arguments (rather than sitting in first-use order) so every
 * existing bindingless positional construction stays valid.
 *
 * @internal
 */
#[Package('framework')]
final class InsertElement extends AbstractLayoutMutation
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly string $type,
        private readonly ?string $parentElementId = null,
        private readonly ?string $slot = null,
        private readonly ?int $index = null,
        private readonly ?AbstractContentSystemBindingSpecificationRegistry $bindingRegistry = null,
        private readonly ?string $bindingSpecificationId = null,
        private readonly ?BindingApplicator $bindingApplicator = null,
    ) {
    }

    public function apply(array $tree): array
    {
        $this->requireRegistered($this->registry, $this->type);

        $bindingSpecificationId = $this->bindingSpecificationId;

        $element = $bindingSpecificationId === null
            ? $this->scaffoldElement($this->registry, $this->type)
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
        $bindingRegistry = $this->bindingRegistry;
        $bindingApplicator = $this->bindingApplicator;

        if ($bindingRegistry === null || $bindingApplicator === null) {
            throw ContentSystemException::mutationBindingCollaboratorsMissing();
        }

        $specification = $bindingRegistry->get($bindingSpecificationId);

        if ($specification === null) {
            throw ContentSystemException::bindingSpecificationNotFound($bindingSpecificationId);
        }

        if ($specification->type() !== $this->type) {
            throw ContentSystemException::bindingTypeMismatch($bindingSpecificationId, $specification->type(), $this->type);
        }

        return $bindingApplicator->apply($this->scaffoldElement($this->registry, $this->type), $specification, $bindingSpecificationId);
    }
}
