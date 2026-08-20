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
 * `Struct`, so it cannot reach a response encoder, and everything downstream of it — the output formats, the
 * {@see \Shopware\Core\Framework\ContentSystem\Event\PostHydrationEvent},
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
 * `ContentSystem\ContentPipeline::load()`, which bridges after the finishing steps — the virtual-root unwrap
 * and the partial extract both run on the rendered model now — and speaks the older model from there on, for
 * the response and the post-hydration event. That call goes when the output layer, the post-hydration event
 * and the Storefront move onto {@see RenderedElement}, and this class is deleted with it. The site above is
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
     * which the DAL write enforces. The read path validates nothing, so a repeated id can arrive here from a
     * raw-SQL or migration write, or from a listener that replaced the tree. The index therefore rejects one
     * instead of letting the last occurrence win: with a repeated id, every occurrence would otherwise lower
     * against the same stored element and wear its component, wiring and style.
     *
     * @param list<StoredElement> $stored the post-plan stored forest the rendered forest was minted from
     * @param list<RenderedElement> $rendered the finished forest, possibly reduced to a subset of `$stored`
     *
     * @return list<ContentElement>
     */
    public function lowerTree(array $stored, array $rendered): array
    {
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
}
