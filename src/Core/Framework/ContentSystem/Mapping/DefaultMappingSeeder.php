<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
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
        private AbstractContentSystemMappingCandidateRegistry $candidates,
        private MappingTypeCompatibility $compatibility,
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
        $specification = isset($targets[$element->id]) ? $this->types->get($element->component) : null;
        $consumers = $element->contextDefinitions->getAllConsumers();
        $changed = false;

        foreach ($specification?->properties() ?? [] as $propertyKey => $property) {
            $source = $property->defaultMapping();
            if (!$property->mappable() || $source === null || isset($consumers[$propertyKey])) {
                continue;
            }

            $candidate = $this->candidates->forRootSource($rootSource)[$source->displayName()] ?? null;
            if ($candidate === null || !$candidate->source->isSameAs($source)) {
                continue;
            }

            $declaredType = $property->type()->type();
            if (
                !$this->compatibility->permits($declaredType, $candidate->valueType)
                || !\in_array($candidate->contextType->value, $property->type()->contextTypes(), true)
            ) {
                continue;
            }

            $consumers[$propertyKey] = new ContextConsumer(
                $candidate->contextType,
                false,
                scope: ConsumerScope::Root,
                projection: $candidate->projection,
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

        return $element->withContextDefinitions($definitions)->withSlots($slots);
    }
}
