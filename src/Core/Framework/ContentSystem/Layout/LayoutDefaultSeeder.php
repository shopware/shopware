<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout;

use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaultProvider;
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
 * Handles both shapes the layout field serializer carries: {@see StoredElement} objects and raw element arrays
 * (Admin / Sync JSON). A stored element is immutable, so seeding it rebuilds the subtree through its `with*()`
 * methods and hands back a new forest rather than filling the one it was given. Shares the per-type rule with the
 * layout mutations via {@see PrimitiveDefaultProvider}.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LayoutDefaultSeeder
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly PrimitiveDefaultProvider $primitiveDefaultProvider,
    ) {
    }

    /**
     * @param list<mixed> $forest
     *
     * @return list<mixed>
     */
    public function seed(array $forest): array
    {
        $seeded = [];

        foreach ($forest as $node) {
            $seeded[] = $this->seedNode($node);
        }

        return $seeded;
    }

    private function seedNode(mixed $node): mixed
    {
        if ($node instanceof StoredElement) {
            return $this->seedElement($node);
        }

        if (\is_array($node)) {
            return $this->seedArray($node);
        }

        return $node;
    }

    /**
     * Rebuilds the element with the missing defaults filled in and every slot child seeded the same way. A key
     * the element already carries keeps its value, an authored null included: the key being present is what
     * decides, so seeding never replaces something the author put there.
     */
    private function seedElement(StoredElement $element): StoredElement
    {
        $properties = $element->properties();

        foreach ($this->defaultsFor($element->component) as $key => $default) {
            if (\array_key_exists($key, $properties)) {
                continue;
            }

            $properties[$key] = StoredValue::fromDecoded($default);
        }

        $slots = [];
        foreach ($element->slots as $name => $children) {
            $slots[$name] = array_map($this->seedElement(...), $children);
        }

        return $element->withProperties($properties)->withSlots($slots);
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
            $defaults = $this->defaultsFor($component);

            if ($defaults !== []) {
                $properties = $node['properties'] ?? [];

                // Seed only into a well-formed property map (string-keyed, or empty). A malformed `properties`
                // (a scalar, or a non-empty list) is left untouched for the write gate to reject, rather than
                // silently discarded or merged into a mixed-key array. PHP's `+` keeps every authored key and
                // fills only the keys the node does not carry.
                if (\is_array($properties) && (!\array_is_list($properties) || $properties === [])) {
                    $node['properties'] = $properties + $defaults;
                }
            }
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

        return $this->primitiveDefaultProvider->forType($this->registry, $component);
    }
}
