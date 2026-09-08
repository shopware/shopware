<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Scaffolding;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\Rendering\ElementLowering;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Brings a stored forest into the state the rendering steps require, and hands stored forests back. Its
 * steps are ordered and internal: a caller asks for a prepared tree, never for one of the steps.
 *
 * The order is language reduction, then placeholder resolution, then the virtual-root wrap, then the partial
 * prune, and finally the scaffolding the finishing steps read. The first two run in FULL mode only. The
 * skeleton response carries a tree's structure and its style, never a property value, so collapsing and
 * resolving into values it discards is work no reader can observe.
 *
 * Language reduction is a value-level collapse inside preparation, which is why it is not named a lowering:
 * that role name belongs to the stored-to-rendered model translation, which {@see ElementLowering} runs. The
 * two passes are distinct.
 *
 * @internal
 */
#[Package('framework')]
final class StoredTreePreparer
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $typeRegistry,
        private readonly VirtualRootWrapper $virtualRootWrapper,
        private readonly PartialRenderer $partialRenderer,
    ) {
    }

    /**
     * @param list<StoredElement> $tree
     */
    public function prepare(
        array $tree,
        RenderingSpecification $specification,
        RenderingMode $mode,
        SalesChannelContext $salesChannelContext,
    ): TreePreparationResult {
        // Placeholders substitute into string values and never descend into a map, so they see a translatable
        // property only once reduction has collapsed it to the selected string.
        if ($mode === RenderingMode::FULL) {
            $tree = $this->reduceTreeLanguage($tree, $salesChannelContext);
            $tree = $this->resolveTreePlaceholders($tree, $specification);
        }

        $virtualRootWrapped = $this->virtualRootWrapper->requiresWrapping($specification, $tree);

        if ($virtualRootWrapped) {
            $tree = [$this->virtualRootWrapper->wrap($tree, $specification)];
        }

        $prePruneForest = $tree;

        $extractTargetId = $this->extractTargetId($specification);
        $tree = $this->pruneToTarget($tree, $extractTargetId);

        return new TreePreparationResult(
            $tree,
            $prePruneForest,
            $this->deriveScaffolding($tree, $extractTargetId, $virtualRootWrapped)
        );
    }

    /**
     * @param list<StoredElement> $tree
     *
     * @return list<StoredElement>
     */
    private function reduceTreeLanguage(array $tree, SalesChannelContext $salesChannelContext): array
    {
        return array_map(
            fn (StoredElement $element): StoredElement => $this->reduceLanguage($element, $salesChannelContext),
            $tree
        );
    }

    /**
     * Collapses one element's translatable properties to the request language and recurses into its slot
     * children. Each element is judged by its own component, so an unregistered parent still has its
     * registered children reduced.
     */
    private function reduceLanguage(StoredElement $element, SalesChannelContext $salesChannelContext): StoredElement
    {
        $slots = [];
        foreach ($element->slots as $slotName => $children) {
            $slots[$slotName] = array_map(
                fn (StoredElement $child): StoredElement => $this->reduceLanguage($child, $salesChannelContext),
                $children
            );
        }

        return $element
            ->withProperties($this->reduceProperties($element, $salesChannelContext))
            ->withSlots($slots);
    }

    /**
     * A component no type declares keeps every value it carries: nothing says which of its keys are
     * translatable, so collapsing one would be a guess.
     *
     * @return array<string, StoredValue>
     */
    private function reduceProperties(StoredElement $element, SalesChannelContext $salesChannelContext): array
    {
        if (!$this->typeRegistry->has($element->component)) {
            return $element->properties();
        }

        $declared = $this->typeRegistry->get($element->component)->properties();
        $chain = $salesChannelContext->getLanguageIdChain();
        $properties = [];

        foreach ($element->properties() as $key => $value) {
            $properties[$key] = isset($declared[$key]) && $declared[$key]->type()->translatable()
                ? $this->selectTranslation($element->id, $key, $value, $chain)
                : $value;
        }

        return $properties;
    }

    /**
     * The value of the first chain entry the map carries, verbatim. A key outside the chain is never
     * selected, so a dangling language id cannot reach serving, and a map carrying no chain entry collapses
     * to the null variant, which the rendered-tree mint skips.
     *
     * The map variant is recognised the way {@see StoredValue::fromDecoded()} assigns it — an unwrapped array
     * whose keys are not a zero-based sequence — because no variant predicate is exposed. Anything else, and a
     * selected entry that is not a string, is an internal fault: every client-supplied path rejects both on a
     * translatable property before a render can reach one.
     *
     * @param non-empty-list<string> $languageIdChain
     */
    private function selectTranslation(string $elementId, string $key, StoredValue $value, array $languageIdChain): StoredValue
    {
        $raw = $value->jsonSerialize();

        if (!\is_array($raw) || array_is_list($raw)) {
            throw ContentSystemException::translationShapeInvalid(
                $elementId,
                $key,
                \is_array($raw) ? 'list' : get_debug_type($raw)
            );
        }

        $map = $value->asMap();

        foreach ($languageIdChain as $languageId) {
            if (!\array_key_exists($languageId, $map)) {
                continue;
            }

            $selected = $map[$languageId];

            // The map shape alone does not make every downstream stage see a plain string: the entry is what
            // is served, so a non-string entry is the same internal fault as a non-map value.
            if (!$selected->isString()) {
                throw ContentSystemException::translationShapeInvalid($elementId, $key, 'a map with a non-string entry');
            }

            return $selected;
        }

        return StoredValue::ofNull();
    }

    /**
     * @param list<StoredElement> $tree
     *
     * @return list<StoredElement>
     */
    private function resolveTreePlaceholders(array $tree, RenderingSpecification $specification): array
    {
        return array_map(
            fn (StoredElement $element): StoredElement => $this->resolvePlaceholders($element, $specification->placeholderValues),
            $tree
        );
    }

    /**
     * The id a partial render extracts, or null when the request addresses the whole layout.
     */
    private function extractTargetId(RenderingSpecification $specification): ?string
    {
        $targetElementId = $specification->targetElementId;

        if ($targetElementId === null || $targetElementId === '') {
            return null;
        }

        return $targetElementId;
    }

    /**
     * Prunes the layout tree to the target element and its dependencies when the `elementId` parameter is present.
     *
     * Pre-hydration tree pruning keeps context-dependent ancestors to preserve data flow; the pipeline's
     * partial extract removes those ancestors after hydration.
     *
     * It runs on the stored forest, before the lowering, so the discarded subtrees never reach the render
     * model at all.
     *
     * @param list<StoredElement> $elements
     *
     * @return list<StoredElement>
     */
    private function pruneToTarget(array $elements, ?string $extractTargetId): array
    {
        if ($extractTargetId === null) {
            return $elements;
        }

        return $this->partialRenderer->pruneToTarget($elements, $extractTargetId);
    }

    /**
     * Records what the finishing steps need to know about the tree these steps produced.
     *
     * `virtualRootSurvivedPrune` is read off the post-prune forest, because the wrap decision alone
     * does not answer whether the virtual root is still there: a partial render addressed at an
     * element that needs no page-level context prunes it away. Element ids are unique across roots,
     * so pruning leaves at most one surviving root whenever a target is set and the first root
     * decides. `$extractTargetId` is passed in already normalised — the prune ran on it.
     *
     * @param list<StoredElement> $elements
     */
    private function deriveScaffolding(array $elements, ?string $extractTargetId, bool $virtualRootWrapped): RenderScaffolding
    {
        $virtualRootSurvivedPrune = $virtualRootWrapped
            && $elements !== []
            && $this->virtualRootWrapper->isVirtualRoot($elements[0]);

        return new RenderScaffolding($virtualRootSurvivedPrune, $extractTargetId);
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
