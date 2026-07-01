<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\Binding\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Applies $bindingSpecificationId's wiring onto $elementId: each `resolves` entry becomes a concrete
 * {@see DataRequirement}, merged into the element's existing data requirements and overwriting the same keys
 * (re-applying a binding over an already-bound key replaces its wiring); each `inputs` entry with a default
 * seeds that primitive property, but only when the element does not already carry the key ({@see
 * ContentElement::hasProperty()}, not a null check, so an authored explicit null is never overwritten); and
 * every wired key's attribution is recorded, also merged and overwriting. Keeps the same id.
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
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider,
    ) {
    }

    public function apply(array $tree): array
    {
        $specification = $this->registry->get($this->bindingSpecificationId);

        if ($specification === null) {
            throw ContentSystemException::bindingSpecificationNotFound($this->bindingSpecificationId);
        }

        $node = $this->findNode($tree, $this->elementId);

        if ($node === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        if ($specification->type() !== $node->getComponent()) {
            throw ContentSystemException::bindingTypeMismatch($this->bindingSpecificationId, $specification->type(), $node->getComponent());
        }

        $dataRequirements = [...$node->getDataRequirements(), ...$this->resolveDataRequirements($specification)];
        $properties = [...$this->seedInputDefaults($node, $specification), ...$node->getProperties()];
        $attributedSpecifications = [...$node->getAttributedSpecifications(), ...$this->attributionFor($specification)];

        $replacement = new ContentElement(
            $node->getId(),
            $node->getComponent(),
            $dataRequirements,
            $properties,
            $node->getSlots(),
            $node->getContextDefinitions(),
            $node->getStyle(),
            $attributedSpecifications,
        );

        $this->affected = [$replacement->getId()];

        return $this->replaceNode($tree, $this->elementId, $replacement);
    }

    /**
     * @return array<string, DataRequirement>
     */
    private function resolveDataRequirements(BindingSpecification $specification): array
    {
        $dataRequirements = [];

        foreach ($specification->resolves() as $key => $binding) {
            $dataRequirements[$key] = new DataRequirement($key, $binding->source(), $this->configSerializerProvider->decode($binding->source(), $binding->config()));
        }

        return $dataRequirements;
    }

    /**
     * Seeds an input's default for every key the target does not already carry. Returned as the base of a
     * property merge (existing properties spread on top), so an authored value — including an explicit null —
     * always wins over a default; {@see ContentElement::hasProperty()} is the presence gate, not a null check.
     *
     * @return array<string, mixed>
     */
    private function seedInputDefaults(ContentElement $node, BindingSpecification $specification): array
    {
        $defaults = [];

        foreach ($specification->inputs() as $key => $input) {
            if (!$input->hasDefault()) {
                continue;
            }

            if ($node->hasProperty($key)) {
                continue;
            }

            $defaults[$key] = $input->default();
        }

        return $defaults;
    }

    /**
     * @return array<string, string>
     */
    private function attributionFor(BindingSpecification $specification): array
    {
        $attribution = [];

        foreach (array_keys($specification->resolves()) as $key) {
            $attribution[$key] = $this->bindingSpecificationId;
        }

        return $attribution;
    }
}
