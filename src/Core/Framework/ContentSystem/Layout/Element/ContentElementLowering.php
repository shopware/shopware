<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Takes a {@see StoredElement} together with the {@see RenderedElement} it was minted into back onto the
 * pre-split {@see ContentElement} model, in that direction only. It is a compatibility bridge rather than a
 * model of its own: {@see RenderedElement} is `final readonly` and deliberately not a `Struct`, so it cannot
 * reach a response encoder, and everything downstream of the render step — the output formats, the
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
 * `ContentSystem\ContentPipeline::load()`, which bridges immediately after the render step and speaks the
 * older model from there on. That call goes when the output layer, the post-hydration event and the Storefront
 * move onto {@see RenderedElement}, and this class is deleted with it. The site above is the whole set: an
 * undeclared call site would make the old model reachable from somewhere the split has already moved past, so
 * a new one is added here with its expiry or not added at all. Validation, mutation and diagnostics bridge
 * nothing: they take and return stored elements.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ContentElementLowering
{
    /**
     * The two forests are paired positionally rather than by id, and the roots are zipped by `array_map`
     * for the same reason the slots below are — see {@see lower()} for why no mismatch handling belongs here.
     *
     * @param list<StoredElement> $stored
     * @param list<RenderedElement> $rendered the forest minted from `$stored`
     *
     * @return list<ContentElement>
     */
    public function lowerTree(array $stored, array $rendered): array
    {
        return array_map($this->lower(...), $stored, $rendered);
    }

    /**
     * The rendered tree is minted 1:1 from this same stored forest, preserving slot names and child order, one
     * line earlier in the caller, so the two trees are structurally identical by construction and each stored
     * slot has a rendered counterpart of the same length. That is why the walk zips them with `array_map`
     * instead of indexing with a guard. Only one violation of that invariant fails loudly: on a length
     * mismatch within a slot, `array_map` pads the shorter list with `null`, `null` hits the typed parameter
     * and a `TypeError` stops the render. The slot names come from the stored side alone, so a rendered slot
     * the stored element lacks is never read and drops silently, while a rendered element missing a stored
     * slot warns on the undefined key before reaching that same `TypeError`. Nothing guards the gap — a
     * `?? []` or any other fallback would silently emit a tree whose properties belong to a different
     * element, and the 1:1 mint is what keeps it unreachable.
     */
    public function lower(StoredElement $stored, RenderedElement $rendered): ContentElement
    {
        $slots = [];
        foreach ($stored->slots as $name => $children) {
            $slots[$name] = new SlotContent(array_map($this->lower(...), $children, $rendered->slots[$name]));
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
}
