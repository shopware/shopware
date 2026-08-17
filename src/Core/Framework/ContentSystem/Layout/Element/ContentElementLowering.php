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
 * It exists to hold the places where a storage-typed tree still meets code written against the older model.
 * Six call sites remain, in two expiry groups:
 *
 * Group one goes when diagnostics moves onto the storage model, because each of these lowers only to feed
 * `ContentSystem\Diagnostics\LayoutDiagnostics`, which still takes the older model:
 *
 * - `ContentSystem\Validation\LayoutGate`, lowering the written tree before diagnosing it.
 * - `ContentSystem\Mutation\MutationPipeline`, lowering the mutated tree.
 * - `ContentSystem\Mutation\PersistedLayoutMutator`, lowering the mutated tree.
 * - `ContentSystem\Api\ContentDiagnoseController`, lowering the decoded draft.
 *
 * Group two goes only when {@see ContentElement} is retired entirely, because each of these feeds the
 * rendering path, which speaks that model for longer than diagnostics does:
 *
 * - `ContentSystem\RenderableLayout::fromEntity()`, lowering the stored layout for serving.
 * - `ContentSystem\Api\ContentPreviewPageBuilder`, which lowers once and hands the one lowered tree to both
 *   `ContentSystem\DraftLayoutChecker::check()` and `ContentSystem\RenderableLayout::create()`. Its diagnosis
 *   half expires with group one, but the render feed keeps the lowering itself alive until the model dies.
 *
 * When both groups are gone this class has no callers left and is deleted with them. The list above is the
 * whole set: an undeclared call site would make the old model reachable from somewhere the split has already
 * moved past, so a new one is added here with its expiry or not added at all. The mutation operations
 * themselves lower nothing: they take and return stored elements.
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
