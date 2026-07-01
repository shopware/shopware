<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Keeps the same element id. {@see ContentElement::hasProperty()} is the presence gate for `inputs`
 * defaults — an authored value, including an explicit null, is never overwritten.
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

        $dataRequirements = array_replace($node->getDataRequirements(), $this->resolveDataRequirements($specification));
        $properties = array_replace($this->seedInputDefaults($node, $specification), $node->getProperties());
        $attributedSpecifications = array_replace($node->getAttributedSpecifications(), $this->attributionFor($specification));

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
            $dataRequirements[$key] = new DataRequirement($key, $binding->source, $this->configSerializerProvider->decode($binding->source, $binding->config));
        }

        return $dataRequirements;
    }

    /**
     * @return array<string, mixed>
     */
    private function seedInputDefaults(ContentElement $node, BindingSpecification $specification): array
    {
        $defaults = [];

        foreach ($specification->inputs() as $key => $input) {
            if (!$input->hasDefault) {
                continue;
            }

            if ($node->hasProperty($key)) {
                continue;
            }

            $defaults[$key] = $input->default;
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
