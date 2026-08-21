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
 * A {@see StoredElement} is immutable, so seeding it rebuilds the subtree through its `with*()` methods and hands
 * back a new forest rather than filling the one it was given. Shares the per-type rule with the layout mutations
 * via {@see PrimitiveDefaultProvider}.
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
     * @param list<StoredElement> $forest
     *
     * @return list<StoredElement>
     */
    public function seed(array $forest): array
    {
        $seeded = [];

        foreach ($forest as $element) {
            $seeded[] = $this->seedElement($element);
        }

        return $seeded;
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
