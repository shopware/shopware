<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;

/**
 * The apply-side of a binding decision, shared by the {@see \Shopware\Core\Framework\ContentSystem\Mutation\Op\BindElement},
 * {@see \Shopware\Core\Framework\ContentSystem\Mutation\Op\InsertElement}, and
 * {@see \Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement} operations. Two application modes share the same
 * `inputs`-seeding and rebuild machinery:
 *
 * - {@see self::apply()} (overwrite): `resolves` become data requirements overwriting the same keys; re-applying a
 *   specification over an already-wired key replaces its wiring. Used for explicit application (`bind-element`, `insert`
 *   with a `bindingSpecificationId`).
 * - {@see self::applyFillOnly()} (fill-only): a `resolves` entry is wired only when the element carries no data
 *   requirement for that key yet, and only those wired keys receive attribution. Used for a type's auto-applied default.
 *
 * Both modes: `inputs` defaults seed a primitive property only when the element does not already carry it
 * ({@see ContentElement::hasProperty()} is the presence gate, so an authored value including an explicit null always wins).
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
        $attributedSpecifications = array_replace($element->getAttributedSpecifications(), $this->attributionFor(array_keys($specification->resolves()), $bindingSpecificationId));

        return $this->rebuild($element, $dataRequirements, $properties, $attributedSpecifications);
    }

    /**
     * Wires a `resolves` entry only into a key the element carries no data requirement for yet, and attributes only
     * those keys — carried or already-bound wiring, and its attribution, is left untouched. The merge is the same
     * existing-wins idiom {@see \Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder} uses for property
     * seeding (`$properties + $defaults`): the element's own map is the left-hand operand, so its keys win.
     */
    public function applyFillOnly(ContentElement $element, BindingSpecification $specification, string $bindingSpecificationId): ContentElement
    {
        $existingDataRequirements = $element->getDataRequirements();
        $wiredKeys = array_diff(array_keys($specification->resolves()), array_keys($existingDataRequirements));

        $dataRequirements = $existingDataRequirements + $this->resolveDataRequirements($specification);
        $properties = array_replace($this->seedInputDefaults($element, $specification), $element->getProperties());
        $attributedSpecifications = $element->getAttributedSpecifications() + $this->attributionFor($wiredKeys, $bindingSpecificationId);

        return $this->rebuild($element, $dataRequirements, $properties, $attributedSpecifications);
    }

    /**
     * @param array<string, DataRequirement> $dataRequirements
     * @param array<string, mixed> $properties
     * @param array<string, string> $attributedSpecifications
     */
    private function rebuild(ContentElement $element, array $dataRequirements, array $properties, array $attributedSpecifications): ContentElement
    {
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
     * @param array<int, string> $keys
     *
     * @return array<string, string>
     */
    private function attributionFor(array $keys, string $bindingSpecificationId): array
    {
        $attribution = [];

        foreach ($keys as $key) {
            $attribution[$key] = $bindingSpecificationId;
        }

        return $attribution;
    }
}
