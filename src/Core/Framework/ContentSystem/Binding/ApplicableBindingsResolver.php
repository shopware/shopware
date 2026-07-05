<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;

/**
 * For every element in a tree, the binding specifications applicable to its `component` — a per-type
 * registry lookup, not a resolution against the element's wiring or ancestry, since a self-persisted
 * loader offer is config-complete at any position.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ApplicableBindingsResolver
{
    public function __construct(
        private readonly AbstractContentSystemBindingSpecificationRegistry $registry,
    ) {
    }

    /**
     * @param list<ContentElement> $tree
     *
     * @return array<string, list<string>> per-element-id map of applicable specification qualified ids
     */
    public function resolve(array $tree): array
    {
        $cache = [];

        return $this->resolveTree($tree, $cache);
    }

    /**
     * @param list<ContentElement> $tree
     * @param array<string, list<string>> $cache component => qualified ids, memoized across this resolve() call
     *                                           so a repeated component within the same tree is looked up in the registry once
     *
     * @return array<string, list<string>>
     */
    private function resolveTree(array $tree, array &$cache): array
    {
        $applicable = [];

        foreach ($tree as $element) {
            $component = $element->getComponent();
            $cache[$component] ??= $this->qualifiedIds($component);
            $applicable[$element->getId()] = $cache[$component];

            // allSlotElements() yields a \Generator with ungeneric (array-key) keys, so [...] alone is an
            // array<>, not a proven list<>; array_values() is required to satisfy resolveTree()'s list<ContentElement>.
            foreach ($this->resolveTree(array_values([...$element->allSlotElements()]), $cache) as $descendantId => $descendantIds) {
                $applicable[$descendantId] = $descendantIds;
            }
        }

        return $applicable;
    }

    /**
     * @return list<string>
     */
    private function qualifiedIds(string $type): array
    {
        return array_map(
            static fn (BindingSpecification $specification): string => $specification->qualifiedId(),
            $this->registry->byType($type),
        );
    }
}
