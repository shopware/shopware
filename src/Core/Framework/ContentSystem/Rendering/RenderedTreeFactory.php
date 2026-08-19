<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\Log\Package;

/**
 * Mints the rendered tree for one stored forest, folding {@see RenderedElementFactory} over it. The element
 * factory mints one element and this mints the forest; {@see \Shopware\Core\Framework\ContentSystem\Layout\Element\RenderedTreeEditor}
 * is the third of the family, editing one that already exists.
 *
 * The fold runs bottom-up, and the type system is what makes it so: `RenderedElementFactory::create()` takes
 * already-minted children as its slot map, so a node cannot be minted before the nodes under it. The
 * recursion assembles each slot first and mints the node last.
 *
 * ONE FOLD SERVES BOTH MODES. The traversal, the slot assembly and the ordering are a single code path, and
 * the mode reaches nothing but the per-node mint call at the bottom of it. That is the whole reason the walk
 * was split in two rather than done in one recursion: a skeleton and a full render of the same layout are
 * the same tree with different property maps, and they stay that way by construction rather than because two
 * traversals were kept in step by review.
 *
 * @internal
 */
#[Package('framework')]
final readonly class RenderedTreeFactory
{
    public function __construct(
        private RenderedElementFactory $elementFactory,
    ) {
    }

    /**
     * @param list<StoredElement> $forest roots in order
     * @param array<string, array<string, mixed>> $loaderValues element id => requirement key => resolved value
     *
     * @throws ContentSystemException when the index was built from a different forest
     *
     * @return list<RenderedElement>
     */
    public function create(
        array $forest,
        ContextDeliveryIndex $deliveries,
        array $loaderValues,
        RenderingMode $mode,
    ): array {
        return array_map(
            fn (StoredElement $root): RenderedElement => $this->mint($root, $deliveries, $loaderValues, $mode),
            $forest
        );
    }

    /**
     * Slot names keep the order the stored element declares them in and children keep their order within a
     * slot, because both come straight from iterating the stored slot map rather than from any collection
     * this class builds.
     *
     * @param array<string, array<string, mixed>> $loaderValues
     */
    private function mint(
        StoredElement $element,
        ContextDeliveryIndex $deliveries,
        array $loaderValues,
        RenderingMode $mode,
    ): RenderedElement {
        $slots = [];
        foreach ($element->slots as $name => $children) {
            $slots[$name] = array_map(
                fn (StoredElement $child): RenderedElement => $this->mint($child, $deliveries, $loaderValues, $mode),
                $children
            );
        }

        if ($mode === RenderingMode::SKELETON) {
            return $this->elementFactory->createStructural($element, $slots);
        }

        $delivery = $deliveries->deliveryFor($element->id);

        return $this->elementFactory->create(
            $element,
            $loaderValues[$element->id] ?? [],
            $delivery->context,
            $delivery->distributionReferencedKeys,
            $slots
        );
    }
}
