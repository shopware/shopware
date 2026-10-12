<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\Binding\Validation\TypeConsistentBindingSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;

/**
 * Applies one {@see BindingSpecification}'s wiring onto a {@see StoredElement}, through the element's own
 * `with*()` copiers. Two modes: {@see self::apply()} overwrites the same `resolves`/attribution keys,
 * {@see self::applyFillOnly()} wires and attributes only keys the element carries no data requirement for yet.
 * Both seed an `inputs` default only when the element does not already carry the property
 * ({@see StoredElement::property()} presence gate, so an authored value always wins, including an explicit null),
 * and both seed it in the storage shape the target property declares — a translatable property's default lands
 * under the anchor language key, resolved through the element-type registry.
 *
 * @internal
 */
#[Package('framework')]
final class BindingApplicator
{
    public function __construct(
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider,
        private readonly AbstractContentSystemElementTypeRegistry $registry,
    ) {
    }

    public function apply(StoredElement $element, BindingSpecification $specification, string $bindingSpecificationId, ?string $rootSource = null): StoredElement
    {
        $dataRequirements = array_replace($element->dataRequirements, $this->resolveDataRequirements($specification->resolves(), $rootSource, $bindingSpecificationId, $element->id));
        $properties = array_replace($this->seedInputDefaults($element, $specification), $element->properties());
        $attributedSpecifications = array_replace($element->attributedSpecifications, $this->attributionFor(array_keys($specification->resolves()), $bindingSpecificationId));

        return $this->rebuild($element, $dataRequirements, $properties, $attributedSpecifications);
    }

    /**
     * Wires a `resolves` entry only into a key the element carries no data requirement for yet, and attributes only
     * those keys — carried or already-bound wiring, and its attribution, is left untouched. The merge is the same
     * existing-wins idiom {@see LayoutDefaultSeeder} uses for property seeding: the element's own value always wins
     * over a wired/seeded one. Only the entries that get written are resolved against the root source, so a scoped
     * config value of a key the element already wires never reaches {@see RootSourceConfigMap::collapse()}.
     */
    public function applyFillOnly(StoredElement $element, BindingSpecification $specification, string $bindingSpecificationId, ?string $rootSource = null): StoredElement
    {
        $existingDataRequirements = $element->dataRequirements;
        $unwired = array_diff_key($specification->resolves(), $existingDataRequirements);

        $dataRequirements = $existingDataRequirements + $this->resolveDataRequirements($unwired, $rootSource, $bindingSpecificationId, $element->id);
        $properties = array_replace($this->seedInputDefaults($element, $specification), $element->properties());
        $attributedSpecifications = $element->attributedSpecifications + $this->attributionFor(array_keys($unwired), $bindingSpecificationId);

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
     * @param array<string, LoaderBinding> $resolves
     *
     * @return array<string, DataRequirement>
     */
    private function resolveDataRequirements(array $resolves, ?string $rootSource, string $bindingSpecificationId, string $elementId): array
    {
        $dataRequirements = [];

        foreach ($resolves as $key => $binding) {
            $config = RootSourceConfigMap::collapse($binding->config, $rootSource, $bindingSpecificationId, $key, $elementId);
            $dataRequirements[$key] = new DataRequirement($key, $binding->loader, $this->configSerializerProvider->decode($binding->loader, $config));
        }

        return $dataRequirements;
    }

    /**
     * Each default takes the storage shape {@see PropertyType::inStoredShape()} states for its target property. A
     * null default on a translatable target stays unwrapped, and {@see TypeConsistentBindingSpecification} rejects
     * that combination at load time.
     *
     * @return array<string, StoredValue>
     */
    private function seedInputDefaults(StoredElement $element, BindingSpecification $specification): array
    {
        // An unregistered component cannot answer whether a key is translatable, so its defaults seed raw.
        $properties = $this->registry->has($element->component) ? $this->registry->get($element->component)->properties() : [];
        $defaults = [];

        foreach ($specification->inputs() as $key => $input) {
            if (!$input->hasDefault) {
                continue;
            }

            if ($element->property($key) !== null) {
                continue;
            }

            $defaults[$key] = StoredValue::fromDecoded(
                isset($properties[$key]) ? $properties[$key]->type()->inStoredShape($input->default) : $input->default
            );
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
