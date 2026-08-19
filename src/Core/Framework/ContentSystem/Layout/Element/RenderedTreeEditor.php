<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element;

use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Applies one per-element transformation to a whole rendered forest, rebuilding it through
 * {@see RenderedElement}'s `with*()` methods. It is the whole-tree half of the rendering extension idiom:
 * a listener that has a rule for a single element hands it here instead of writing the recursion itself,
 * and no node is ever edited in place.
 *
 * A node's slot children are mapped before the node itself, and the node handed to the mapper already
 * carries those mapped children, so whatever the mapper returns is what ends up in the tree. Elements a
 * mapper introduces are not themselves mapped — one pass is one pass.
 */
#[Package('framework')]
final readonly class RenderedTreeEditor
{
    /**
     * @param list<RenderedElement> $tree
     * @param callable(RenderedElement): RenderedElement $mapper
     *
     * @return list<RenderedElement>
     */
    public function mapNodes(array $tree, callable $mapper): array
    {
        return array_map(
            fn (RenderedElement $element): RenderedElement => $this->mapNode($element, $mapper),
            $tree
        );
    }

    /**
     * @param callable(RenderedElement): RenderedElement $mapper
     */
    private function mapNode(RenderedElement $element, callable $mapper): RenderedElement
    {
        if ($element->slots === []) {
            return $mapper($element);
        }

        $slots = [];
        foreach ($element->slots as $name => $children) {
            $slots[$name] = $this->mapNodes($children, $mapper);
        }

        return $mapper($element->withSlots($slots));
    }
}
