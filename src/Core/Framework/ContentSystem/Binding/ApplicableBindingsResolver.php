<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;

/**
 * For every element in a tree, the binding specifications applicable to its type: a specification declared for a
 * type is always applicable at any position of that type (a self-persisted loader offer is config-complete
 * regardless of position), so this is a per-element lookup keyed by the element's `component`, not a resolution
 * against its actual wiring or ancestry. A response-assembly-layer concern only — never consulted by
 * `Diagnostics/LayoutDiagnostics::analyze()` or the write gate.
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
        $applicable = [];

        foreach ($tree as $element) {
            $applicable[$element->getId()] = $this->qualifiedIds($element->getComponent());

            foreach ($this->resolve(array_values([...$element->allSlotElements()])) as $descendantId => $descendantIds) {
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
            static fn (BindingSpecification $specification): string => $specification->source() . ':' . $specification->id(),
            $this->registry->byType($type),
        );
    }
}
