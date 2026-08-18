<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Scaffolding;

use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * Brings a stored forest into the state the rendering steps require, and hands a stored forest back. Its
 * steps are ordered and internal: a caller asks for a prepared tree, never for one of the steps.
 *
 * Placeholder resolution runs in FULL mode only. The skeleton response carries a tree's structure and its
 * style, never a property value, so resolving into values it discards is work no reader can observe.
 *
 * @internal
 */
#[Package('framework')]
final class StoredTreePreparer
{
    /**
     * @param list<StoredElement> $tree
     *
     * @return list<StoredElement>
     */
    public function prepare(array $tree, RenderingSpecification $specification, RenderingMode $mode): array
    {
        if ($mode !== RenderingMode::FULL) {
            return $tree;
        }

        return array_map(
            fn (StoredElement $element): StoredElement => $this->resolvePlaceholders($element, $specification->placeholderValues),
            $tree
        );
    }

    /**
     * Rewrites the string values of an element's own property map and recurses into its slot children.
     *
     * A list or map value is handed on untouched, string leaves inside it included: a placeholder is a
     * property of the authored value, and reaching into a container would resolve tokens the authoring
     * surface never offered to resolve.
     */
    private function resolvePlaceholders(StoredElement $element, PlaceholderValues $values): StoredElement
    {
        $properties = [];
        foreach ($element->properties() as $key => $value) {
            $properties[$key] = $value->isString()
                ? StoredValue::ofString($this->substitute($value->asString(), $values))
                : $value;
        }

        $slots = [];
        foreach ($element->slots as $slotName => $children) {
            $slots[$slotName] = array_map(
                fn (StoredElement $child): StoredElement => $this->resolvePlaceholders($child, $values),
                $children
            );
        }

        return $element->withProperties($properties)->withSlots($slots);
    }

    /**
     * One pass over the declared keys, no recursion into what a substitution produced. A `{{token}}` whose
     * key carries no value stays verbatim, so an unresolved placeholder is visible rather than blanked.
     */
    private function substitute(string $input, PlaceholderValues $values): string
    {
        foreach ($values->all() as $key => $value) {
            $input = str_replace('{{' . $key . '}}', (string) $value, $input);
        }

        return $input;
    }
}
