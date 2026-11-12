<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout;

use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\StoredDefaultProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * Seeds an element forest's type defaults into stored properties at the DAL write boundary, so every tree written to
 * content_layout carries its type defaults regardless of how it was built (direct DAL write, Sync API, import,
 * fixtures), including the paths that never pass through the layout mutations. Per node it adds each property default
 * {@see StoredDefaultProvider} returns for the node's component type whose top-level key is absent: a primitive
 * default, or the map of nested member defaults. A present key keeps its value, an authored null or a partial map
 * included, so no member default is merged into a map that is present. An unregistered component is left untouched
 * (the write gate reports that separately). It then recurses every slot's children.
 *
 * A {@see StoredElement} is immutable, so seeding it rebuilds the subtree through its `with*()` methods and hands
 * back a new forest rather than filling the one it was given. Shares the per-type rule with the layout mutations
 * via {@see StoredDefaultProvider}.
 *
 * @phpstan-import-type PropertyDefault from StoredDefaultProvider
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
        private readonly StoredDefaultProvider $storedDefaultProvider,
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
     * @return array<string, PropertyDefault>
     */
    private function defaultsFor(string $component): array
    {
        if (!$this->registry->has($component)) {
            return [];
        }

        return $this->storedDefaultProvider->forType($this->registry, $component);
    }
}
