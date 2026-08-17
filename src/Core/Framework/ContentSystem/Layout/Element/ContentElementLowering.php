<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\Log\Package;

/**
 * Takes a {@see StoredElement} down onto the {@see ContentElement} model, in that direction only. Property
 * values are unwrapped out of their {@see StoredValue} envelope into raw PHP values and each slot's children
 * are wrapped back into a {@see SlotContent} collection; the wiring, style and attribution ride across as they
 * are. No inverse exists here, and none is wanted: the storage side is where the typed model lives, and a
 * conversion back into it would let an untyped element re-enter it.
 *
 * It exists to hold the places where a storage-typed tree still meets code written against the older model,
 * and each place carries its own deletion duty:
 *
 * - The serving seam, `ContentSystem\RenderableLayout::fromEntity()`. It goes when the rendered element model
 *   lands and serving reads that model instead of a {@see ContentElement}.
 * - The persisted mutator, `ContentSystem\Mutation\PersistedLayoutMutator`, which hands the loaded tree to the
 *   mutation operations. It goes when those operations take and return stored elements.
 * - The write-validation gate, `ContentSystem\Validation\LayoutGate`, which lowers the tree it was handed before
 *   diagnosing it. It goes together with the persisted mutator, when diagnostics moves onto the storage model.
 *
 * When all of them are gone this class has no callers left and is deleted with them. The list above is the
 * whole set: an undeclared call site would make the old model reachable from somewhere the split has already
 * moved past, so a new one is added here with its expiry or not added at all.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ContentElementLowering
{
    /**
     * @param list<StoredElement> $tree
     *
     * @return list<ContentElement>
     */
    public function lowerTree(array $tree): array
    {
        return array_map($this->lower(...), $tree);
    }

    public function lower(StoredElement $element): ContentElement
    {
        $slots = [];
        foreach ($element->slots as $name => $children) {
            $slots[$name] = new SlotContent(array_map($this->lower(...), $children));
        }

        return new ContentElement(
            $element->id,
            $element->component,
            $element->dataRequirements,
            array_map(static fn (StoredValue $value): mixed => $value->jsonSerialize(), $element->properties()),
            $slots,
            $element->contextDefinitions,
            $element->style,
            $element->attributedSpecifications,
        );
    }
}
