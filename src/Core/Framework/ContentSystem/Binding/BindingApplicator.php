<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Shopware\Core\Framework\Log\Package;

/**
 * Applies one {@see BindingSpecification}'s wiring onto a {@see StoredElement}, through the element's own
 * `with*()` copiers. Two modes: {@see self::apply()} overwrites the same `resolves`/attribution keys,
 * {@see self::applyFillOnly()} wires and attributes only keys the element carries no data requirement for yet.
 * Both seed an `inputs` default only when the element does not already carry the property
 * ({@see StoredElement::property()} presence gate, so an authored value always wins, including an explicit null).
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

    public function apply(StoredElement $element, BindingSpecification $specification, string $bindingSpecificationId): StoredElement
    {
        $dataRequirements = array_replace($element->dataRequirements, $this->resolveDataRequirements($specification));
        $properties = array_replace($this->seedInputDefaults($element, $specification), $element->properties());
        $attributedSpecifications = array_replace($element->attributedSpecifications, $this->attributionFor(array_keys($specification->resolves()), $bindingSpecificationId));

        return $this->rebuild($element, $dataRequirements, $properties, $attributedSpecifications);
    }

    /**
     * Wires a `resolves` entry only into a key the element carries no data requirement for yet, and attributes only
     * those keys — carried or already-bound wiring, and its attribution, is left untouched. The merge is the same
     * existing-wins idiom {@see LayoutDefaultSeeder} uses for property seeding: the element's own value always wins
     * over a wired/seeded one.
     */
    public function applyFillOnly(StoredElement $element, BindingSpecification $specification, string $bindingSpecificationId): StoredElement
    {
        $existingDataRequirements = $element->dataRequirements;
        $wiredKeys = array_diff(array_keys($specification->resolves()), array_keys($existingDataRequirements));

        $dataRequirements = $existingDataRequirements + $this->resolveDataRequirements($specification);
        $properties = array_replace($this->seedInputDefaults($element, $specification), $element->properties());
        $attributedSpecifications = $element->attributedSpecifications + $this->attributionFor($wiredKeys, $bindingSpecificationId);

        return $this->rebuild($element, $dataRequirements, $properties, $attributedSpecifications);
    }

    /**
     * @param array<string, DataRequirement> $dataRequirements
     * @param array<string, StoredValue> $properties
     * @param array<string, string> $attributedSpecifications
     */
    private function rebuild(StoredElement $element, array $dataRequirements, array $properties, array $attributedSpecifications): StoredElement
    {
        return $element
            ->withDataRequirements($dataRequirements)
            ->withProperties($properties)
            ->withAttributedSpecifications($attributedSpecifications);
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
     * @return array<string, StoredValue>
     */
    private function seedInputDefaults(StoredElement $element, BindingSpecification $specification): array
    {
        $defaults = [];

        foreach ($specification->inputs() as $key => $input) {
            if (!$input->hasDefault) {
                continue;
            }

            if ($element->property($key) !== null) {
                continue;
            }

            $defaults[$key] = StoredValue::fromDecoded($input->default);
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
