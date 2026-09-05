<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Scaffolding;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * Brings a stored forest into the state the rendering steps require, and hands stored forests back. Its
 * steps are ordered and internal: a caller asks for a prepared tree, never for one of the steps.
 *
 * The order is placeholder resolution, then the virtual-root wrap, then the partial prune, and finally the
 * scaffolding the finishing steps read. Placeholder resolution runs in FULL mode only. The skeleton response
 * carries a tree's structure and its style, never a property value, so resolving into values it discards is
 * work no reader can observe.
 *
 * @internal
 */
#[Package('framework')]
final class StoredTreePreparer
{
    public function __construct(
        private readonly VirtualRootWrapper $virtualRootWrapper,
        private readonly PartialRenderer $partialRenderer,
        private readonly DataLoaderConfigSerializerProvider $configSerializers,
    ) {
    }

    /**
     * @param list<StoredElement> $tree
     */
    public function prepare(array $tree, RenderingSpecification $specification, RenderingMode $mode): TreePreparationResult
    {
        $tree = $this->resolveTreePlaceholders($tree, $specification, $mode);

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
    private function resolveTreePlaceholders(array $tree, RenderingSpecification $specification, RenderingMode $mode): array
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
     * Rewrites the string values of an element's own property map, resolves the placeholders in each of its
     * data requirement's loader config, and recurses into its slot children.
     *
     * A list or map property value is handed on untouched, string leaves inside it included: a placeholder is
     * a property of the authored value, and reaching into a container would resolve tokens the authoring
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

        $dataRequirements = [];
        foreach ($element->dataRequirements as $key => $requirement) {
            $dataRequirements[$key] = new DataRequirement(
                $requirement->key,
                $requirement->source,
                $this->configSerializers->decode(
                    $requirement->source,
                    $this->configSerializers->encode($requirement->source, $requirement->config),
                    $values,
                ),
            );
        }

        $slots = [];
        foreach ($element->slots as $slotName => $children) {
            $slots[$slotName] = array_map(
                fn (StoredElement $child): StoredElement => $this->resolvePlaceholders($child, $values),
                $children
            );
        }

        return $element->withProperties($properties)->withDataRequirements($dataRequirements)->withSlots($slots);
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
