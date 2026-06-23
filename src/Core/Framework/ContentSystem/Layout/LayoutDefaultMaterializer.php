<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaults;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * Seeds an element forest's primitive type defaults into stored properties at the DAL write boundary, so every tree
 * written to content_layout carries its type defaults regardless of how it was built (direct DAL write, Sync API,
 * import, fixtures) — the paths that never pass through the layout mutations. Per node it fills each primitive
 * property of the node's component type whose default is non-null and whose key is absent, then recurses every
 * slot's children; an existing value is never overwritten and an unregistered component is left untouched (the write
 * gate reports that separately).
 *
 * Handles both shapes the layout field serializer carries: hydrated {@see ContentElement} objects (seeded in place)
 * and raw element arrays (Admin / Sync JSON, which the serializer does not recurse). Shares the per-type rule with
 * the layout mutations via {@see PrimitiveDefaults}.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LayoutDefaultMaterializer
{
    private readonly PrimitiveDefaults $primitiveDefaults;

    public function __construct(private readonly AbstractContentSystemElementTypeRegistry $registry)
    {
        $this->primitiveDefaults = new PrimitiveDefaults();
    }

    /**
     * @param list<mixed> $forest
     *
     * @return list<mixed>
     */
    public function materialize(array $forest): array
    {
        $seeded = [];

        foreach ($forest as $node) {
            $seeded[] = $this->seedNode($node);
        }

        return $seeded;
    }

    private function seedNode(mixed $node): mixed
    {
        if ($node instanceof ContentElement) {
            return $this->seedElement($node);
        }

        if (\is_array($node)) {
            return $this->seedArray($node);
        }

        return $node;
    }

    private function seedElement(ContentElement $node): ContentElement
    {
        foreach ($this->defaultsFor($node->getComponent()) as $key => $default) {
            if (!$node->hasProperty($key)) {
                $node->setProperty($key, $default);
            }
        }

        foreach ($node->getSlots() as $slot) {
            foreach ($slot->getElements() as $child) {
                $this->seedElement($child);
            }
        }

        return $node;
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return array<array-key, mixed>
     */
    private function seedArray(array $node): array
    {
        $component = $node['component'] ?? null;

        if (\is_string($component)) {
            $properties = $node['properties'] ?? [];
            // PHP's `+` keeps every authored key and fills only the keys the node does not carry.
            $node['properties'] = (\is_array($properties) ? $properties : []) + $this->defaultsFor($component);
        }

        $slots = $node['slots'] ?? null;

        if (\is_array($slots)) {
            $node['slots'] = array_map($this->seedSlot(...), $slots);
        }

        return $node;
    }

    private function seedSlot(mixed $children): mixed
    {
        if (!\is_array($children)) {
            return $children;
        }

        return array_map($this->seedNode(...), $children);
    }

    /**
     * @return array<string, string|int|float|bool>
     */
    private function defaultsFor(string $component): array
    {
        if (!$this->registry->has($component)) {
            return [];
        }

        return $this->primitiveDefaults->forType($this->registry, $component);
    }
}
