<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;

/**
 * The apply-side of a binding decision, shared by the {@see \Shopware\Core\Framework\ContentSystem\Mutation\Op\BindElement}
 * and {@see \Shopware\Core\Framework\ContentSystem\Mutation\Op\InsertElement} operations. Rebuilds an element carrying the
 * specification's wiring: `resolves` become data requirements overwriting the same keys, `inputs` defaults seed a primitive
 * property only when the element does not already carry it ({@see ContentElement::hasProperty()} is the presence gate, so an
 * authored value including an explicit null always wins), and every wired key's attribution is merged overwriting.
 *
 * Rebuilds via the {@see ContentElement} constructor; it never mutates the input element (the mutation immutability invariant).
 *
 * @internal
 */
#[Package('framework')]
final class BindingApplicator
{
    public function __construct(
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider,
    ) {
    }

    public function apply(ContentElement $element, BindingSpecification $specification, string $bindingSpecificationId): ContentElement
    {
        $dataRequirements = array_replace($element->getDataRequirements(), $this->resolveDataRequirements($specification));
        $properties = array_replace($this->seedInputDefaults($element, $specification), $element->getProperties());
        $attributedSpecifications = array_replace($element->getAttributedSpecifications(), $this->attributionFor($specification, $bindingSpecificationId));

        return new ContentElement(
            $element->getId(),
            $element->getComponent(),
            $dataRequirements,
            $properties,
            $element->getSlots(),
            $element->getContextDefinitions(),
            $element->getStyle(),
            $attributedSpecifications,
        );
    }

    /**
     * @return array<string, DataRequirement>
     */
    private function resolveDataRequirements(BindingSpecification $specification): array
    {
        $dataRequirements = [];

        foreach ($specification->resolves() as $key => $binding) {
            $dataRequirements[$key] = new DataRequirement($key, $binding->loader, $this->configSerializerProvider->decode($binding->loader, $binding->config));
        }

        return $dataRequirements;
    }

    /**
     * @return array<string, mixed>
     */
    private function seedInputDefaults(ContentElement $element, BindingSpecification $specification): array
    {
        $defaults = [];

        foreach ($specification->inputs() as $key => $input) {
            if (!$input->hasDefault) {
                continue;
            }

            if ($element->hasProperty($key)) {
                continue;
            }

            $defaults[$key] = $input->default;
        }

        return $defaults;
    }

    /**
     * @return array<string, string>
     */
    private function attributionFor(BindingSpecification $specification, string $bindingSpecificationId): array
    {
        $attribution = [];

        foreach (array_keys($specification->resolves()) as $key) {
            $attribution[$key] = $bindingSpecificationId;
        }

        return $attribution;
    }
}
