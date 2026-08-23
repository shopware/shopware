<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\RenderedTreeEditor;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Output\Index\ValueProvenance;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\Log\Package;

/**
 * Mints the rendered tree for one stored forest, folding {@see RenderedElementFactory} over it. The element
 * factory mints one element and this mints the forest; {@see RenderedTreeEditor}
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
     * The provenance of every element the fold mints is collected into one forest-wide map on the way out, so
     * a later stage reads it by element id without walking the tree again.
     *
     * @param list<StoredElement> $forest roots in order
     * @param array<string, array<string, ResolvedLoaderValue>> $loaderValues element id => requirement key => resolved value
     *
     * @throws ContentSystemException when the index was built from a different forest
     */
    public function create(
        array $forest,
        ContextDeliveryIndex $deliveries,
        array $loaderValues,
        RenderingMode $mode,
    ): LoweringResult {
        $tree = [];
        $provenance = [];

        foreach ($forest as $root) {
            $tree[] = $this->mint($root, $deliveries, $loaderValues, $mode, $provenance);
        }

        return new LoweringResult($tree, $provenance);
    }

    /**
     * Slot names keep the order the stored element declares them in and children keep their order within a
     * slot, because both come straight from iterating the stored slot map rather than from any collection
     * this class builds.
     *
     * @param array<string, array<string, ResolvedLoaderValue>> $loaderValues
     * @param array<string, array<string, ValueProvenance>> $provenance collected across the whole fold
     */
    private function mint(
        StoredElement $element,
        ContextDeliveryIndex $deliveries,
        array $loaderValues,
        RenderingMode $mode,
        array &$provenance,
    ): RenderedElement {
        // A plain loop rather than array_map: the accumulator is passed by reference, and an arrow function
        // would capture it BY VALUE, so every child's provenance would be written into a copy and lost while
        // the rendered tree still came out correct.
        $slots = [];
        foreach ($element->slots as $name => $children) {
            $minted = [];
            foreach ($children as $child) {
                $minted[] = $this->mint($child, $deliveries, $loaderValues, $mode, $provenance);
            }

            $slots[$name] = $minted;
        }

        $minted = $this->mintElement($element, $deliveries, $loaderValues, $mode, $slots);

        if ($minted->provenance !== []) {
            $provenance[$element->id] = $minted->provenance;
        }

        return $minted->element;
    }

    /**
     * @param array<string, array<string, ResolvedLoaderValue>> $loaderValues
     * @param array<string, list<RenderedElement>> $slots
     */
    private function mintElement(
        StoredElement $element,
        ContextDeliveryIndex $deliveries,
        array $loaderValues,
        RenderingMode $mode,
        array $slots,
    ): ElementMintResult {
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
