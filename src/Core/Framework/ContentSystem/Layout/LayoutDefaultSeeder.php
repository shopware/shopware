<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Visitor\DefaultSeedingVisitor;
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
 * Handles both shapes the layout field serializer carries: hydrated {@see ContentElement} objects (seeded in place)
 * and raw element arrays (Admin / Sync JSON, which the serializer does not recurse). Shares the per-type rule with
 * the layout mutations via {@see PrimitiveDefaultProvider}.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LayoutDefaultSeeder
{
    private readonly DefaultSeedingVisitor $seedingVisitor;

    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly PrimitiveDefaultProvider $primitiveDefaultProvider,
    ) {
        $this->seedingVisitor = new DefaultSeedingVisitor($registry, $primitiveDefaultProvider);
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
        if ($node instanceof ContentElement) {
            $node->traverse($this->seedingVisitor);

            return $node;
        }

        if (\is_array($node)) {
            return $this->seedArray($node);
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

        return $this->primitiveDefaultProvider->forType($this->registry, $component);
    }
}
