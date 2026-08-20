<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Takes a finished {@see RenderedElement} forest together with the {@see StoredElement} forest it was minted
 * from back onto the pre-split {@see ContentElement} model, in that direction only. It is a compatibility
 * bridge rather than a model of its own: {@see RenderedElement} is `final readonly` and deliberately not a
 * `Struct`, so it cannot reach a response encoder, and everything downstream of it — the output formats,
 * {@see \Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage} and the Storefront components — still
 * speaks the older model.
 *
 * Property values come from the rendered side alone. {@see \Shopware\Core\Framework\ContentSystem\Rendering\RenderedElementFactory}
 * has already derived the render namespace, unwrapped every {@see StoredValue} and merged the loaded and the
 * delivered values into it, so this class unwraps nothing itself and adds nothing to that map.
 *
 * Everything else — `id`, `component`, `dataRequirements`, the context definitions, `style` and
 * `attributedSpecifications` — is read off the stored element, because the response wire this pipeline still
 * produces emits `dataRequirements`, `providesContext` and `acceptsContext` per element, and
 * {@see \Shopware\Core\Framework\ContentSystem\Layout\Element\Visitor\PropertiesExtractionVisitor} reads
 * `dataRequirements` to pick the reference-id grammar of the decomposed format. Reproducing today's serving
 * output therefore needs those fields, and the rendered model deliberately carries none of them. Reading
 * attribution here is not a hole in the rendered model's "carries no attribution" rule: the rendered element
 * still carries none, the bridge just walks both trees at once.
 *
 * It exists to hold the one place where the split models still meet code written against the older one:
 * `ContentSystem\ContentPipeline::load()`, which bridges last — the virtual-root unwrap, the partial extract
 * and the finalization event all run on the rendered model ahead of it — and speaks the older model from
 * there on, for the response alone. That call goes when the output layer and the Storefront move onto
 * {@see RenderedElement}, and this class is deleted with it. The site above is
 * the whole set: an undeclared call site would make the old model reachable from somewhere the split has
 * already moved past, so a new one is added here with its expiry or not added at all. Validation, mutation
 * and diagnostics bridge nothing: they take and return stored elements.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ContentElementLowering
{
    /**
     * The two forests are paired by element id, not by position. An element id survives the stored-to-rendered
     * conversion unchanged, and the finishing steps between the render step and this bridge only ever REMOVE
     * nodes — the virtual-root unwrap drops the wrapper, the partial extract keeps one subtree — never rename
     * or mint one. Every id in the finished rendered forest is therefore present in the stored forest that
     * forest was minted from, while position and root count are not: positional pairing broke the moment the
     * finishing steps moved ahead of the bridge.
     *
     * Pairing by id needs ids to be unique across the forest, which is the rendered model's own contract and
     * which the DAL write enforces. The read path validates nothing, so BOTH forests are checked here, each
     * for its own reason. A stored forest can repeat an id after a raw-SQL or migration write, or after a
     * preparation listener replaced the tree; the index rejects a repeat instead of letting the last
     * occurrence win, because every occurrence would otherwise lower against the same stored element and wear
     * its component, wiring and style. A rendered forest can repeat one after a finalization listener replaced
     * the tree; the pre-pass rejects that, because both occurrences would pair with the same stored twin and
     * the response would carry the id twice — the id partial extraction, `data-element-id` and the decomposed
     * format's assignments all key on.
     *
     * @param list<StoredElement> $stored the post-plan stored forest the rendered forest was minted from
     * @param list<RenderedElement> $rendered the finished forest, possibly reduced to a subset of `$stored`
     *
     * @return list<ContentElement>
     */
    public function lowerTree(array $stored, array $rendered): array
    {
        $seen = [];
        $this->rejectRepeatedRenderedIds($rendered, $seen);

        $index = [];
        $this->indexForest($stored, $index);

        return array_map(fn (RenderedElement $element): ContentElement => $this->lower($element, $index), $rendered);
    }

    /**
     * The rendered side drives structure and slot names, because it is the side the finishing steps shaped.
     * A rendered id the index does not hold breaks the invariant above and throws: a `?? []`, a nullable
     * stored twin or skipping the element would each silently emit a tree whose properties belong to a
     * different element.
     *
     * @param array<string, StoredElement> $index
     */
    private function lower(RenderedElement $rendered, array $index): ContentElement
    {
        $stored = $index[$rendered->id] ?? throw ContentSystemException::invalidMapValue(
            'Stored element index',
            $rendered->id,
            StoredElement::class,
            'no such stored element'
        );

        $slots = [];
        foreach ($rendered->slots as $name => $children) {
            $slots[$name] = new SlotContent(
                array_map(fn (RenderedElement $child): ContentElement => $this->lower($child, $index), $children)
            );
        }

        return new ContentElement(
            $stored->id,
            $stored->component,
            $stored->dataRequirements,
            $rendered->properties,
            $slots,
            $stored->contextDefinitions,
            $stored->style,
            $stored->attributedSpecifications,
        );
    }

    /**
     * @param list<StoredElement> $stored
     * @param array<string, StoredElement> $index
     */
    private function indexForest(array $stored, array &$index): void
    {
        foreach ($stored as $element) {
            if (isset($index[$element->id])) {
                throw ContentSystemException::duplicateElementId($element->id);
            }

            $index[$element->id] = $element;

            foreach ($element->slots as $children) {
                $this->indexForest($children, $index);
            }
        }
    }

    /**
     * A seen-set rather than a map, because the rendered side is only checked and never looked up: the stored
     * walk is what needs a map. Kept a separate walk from `indexForest()` so that each forest visibly carries
     * its own guard.
     *
     * @param list<RenderedElement> $rendered
     * @param array<string, true> $seen
     */
    private function rejectRepeatedRenderedIds(array $rendered, array &$seen): void
    {
        foreach ($rendered as $element) {
            if (isset($seen[$element->id])) {
                throw ContentSystemException::duplicateElementId($element->id);
            }

            $seen[$element->id] = true;

            foreach ($element->slots as $children) {
                $this->rejectRepeatedRenderedIds($children, $seen);
            }
        }
    }
}
