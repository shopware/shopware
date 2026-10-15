<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\StorefrontContext\StorefrontContextLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\StorefrontContextMappingCandidateProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * Seeds a property's declared default mapping when the layout root source offers it. An existing consumer always
 * wins, so an explicit author mapping is preserved. With no matching root source, the property remains unmapped.
 *
 * @internal
 */
#[Package('framework')]
final readonly class DefaultMappingSeeder
{
    public function __construct(
        private AbstractContentSystemElementTypeRegistry $types,
        private StoredMappingInspector $mappingInspector,
    ) {
    }

    /**
     * @param list<string> $elementIds newly created elements in this mutation
     */
    public function seed(StoredTree $tree, ?string $rootSource, array $elementIds): StoredTree
    {
        if ($rootSource === null || $rootSource === '' || $elementIds === []) {
            return $tree;
        }

        $targets = array_fill_keys($elementIds, true);

        return new StoredTree(array_map(fn (StoredElement $element): StoredElement => $this->seedElement($element, $rootSource, $targets), $tree->roots));
    }

    /**
     * @param array<string, true> $targets
     */
    private function seedElement(StoredElement $element, string $rootSource, array $targets): StoredElement
    {
        $specification = isset($targets[$element->id]) && $this->types->has($element->component)
            ? $this->types->get($element->component)
            : null;
        $consumers = $element->contextDefinitions->getAllConsumers();
        $requirements = $element->dataRequirements;
        $changed = false;

        foreach ($specification?->properties() ?? [] as $propertyKey => $property) {
            $source = $property->defaultMapping();
            if (!$property->mappable() || $source === null || isset($consumers[$propertyKey])) {
                continue;
            }

            $candidate = $this->mappingInspector->admitProperty($element->component, $propertyKey, $source, $rootSource);
            if ($candidate instanceof ContentSystemException) {
                continue;
            }

            if (StorefrontContextMappingCandidateProvider::isApprovedSource($candidate->source)) {
                $existing = $requirements[StorefrontContextLoader::REQUIREMENT_KEY] ?? null;
                if ($existing !== null && ($existing->source !== StorefrontContextLoader::SOURCE || $existing->config->jsonSerialize() !== [])) {
                    continue;
                }

                $requirements[StorefrontContextLoader::REQUIREMENT_KEY] = StorefrontContextLoader::requirement();
            }

            $consumers[$propertyKey] = new ContextConsumer(
                $candidate->contextType,
                false,
                scope: ConsumerScope::Root,
                source: $candidate->source,
            );
            $changed = true;
        }

        $slots = [];
        foreach ($element->slots as $slot => $children) {
            $slots[$slot] = array_map(fn (StoredElement $child): StoredElement => $this->seedElement($child, $rootSource, $targets), $children);
        }

        if (!$changed && $slots === $element->slots) {
            return $element;
        }

        $definitions = $changed
            ? new ContextDefinitions($element->contextDefinitions->getAllProviders(), $consumers)
            : $element->contextDefinitions;

        return $element->withContextDefinitions($definitions)->withDataRequirements($requirements)->withSlots($slots);
    }
}
