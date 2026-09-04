<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Turns one stored forest into the rendered forest it serves as. The three render layers underneath it each
 * answer one question about one thing — {@see ElementDataResolver} runs a single element's data requirements,
 * {@see ContextDeliveryResolver} answers what a whole forest's elements received, {@see RenderedTreeFactory}
 * mints the tree — and this is the one place that owns the step as a whole. The forest-wide data walk lives
 * here because no other layer is in a position to do it: the data resolver sees one element at a time, and
 * the delivery resolver takes the loader values already collected.
 *
 * Ordering is the substance of this class. In FULL mode loading completes over the WHOLE forest before any
 * distribution starts, because a provider may hand a loaded value on to a child: process an element's
 * distribution before a later element has loaded, and the value that element was going to provide is not
 * there yet. That is why {@see ContextDeliveryResolver::resolve()} takes the loader values as an argument
 * rather than resolving them itself, and this class is what fills that argument.
 *
 * The data walk is pre-order and descends slot by slot: an element loads before the elements under it, and
 * each slot's children load in declaration order. What that order buys is narrower than it looks:
 * {@see RenderingCacheContext::disable()} is an irreversible flag and {@see RenderingCacheContext::addTags()}
 * dedupes, so neither the disabled state nor the tag SET depends on it — only the tag list's first-occurrence
 * order does, along with whatever order a loader's own side effects are sensitive to. Those two are what the
 * order is fixed for; nothing else in the module's output depends on it.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ElementLowering
{
    public function __construct(
        private ElementDataResolver $dataResolver,
        private ContextDeliveryResolver $deliveryResolver,
        private RenderedTreeFactory $treeFactory,
    ) {
    }

    /**
     * SKELETON resolves no data and computes no deliveries, and the mint below is handed an empty index and
     * an empty loader-value map. That is not an optimisation: a skeleton is a structural answer, so no loader
     * runs, nothing is contributed to `$cacheContext`, and {@see RenderedTreeFactory} reads neither argument
     * in that mode. The two modes differ in data resolution alone — the traversal that shapes the tree is one
     * code path either way.
     *
     * @param list<StoredElement> $forest roots in order
     *
     * @throws ContentSystemException when a required consumer's path cannot be resolved
     */
    public function lower(
        array $forest,
        RenderingMode $mode,
        SalesChannelContext $context,
        Request $request,
        RenderingCacheContext $cacheContext,
    ): LoweringResult {
        if ($mode === RenderingMode::SKELETON) {
            return $this->treeFactory->create($forest, new ContextDeliveryIndex(), [], $mode);
        }

        $loaderValues = $this->resolveLoaderValues($forest, $context, $request, $cacheContext);

        // Context distribution is about dataflow, not about where a value came from, so it sees the plain
        // values: a provider hands a child what it renders, and the identity beside it means nothing there.
        $deliveries = $this->deliveryResolver->resolve($forest, $this->plainValues($loaderValues));

        return $this->treeFactory->create($forest, $deliveries, $loaderValues, $mode);
    }

    /**
     * @param array<string, array<string, ResolvedLoaderValue>> $loaderValues
     *
     * @return array<string, array<string, mixed>>
     */
    private function plainValues(array $loaderValues): array
    {
        return array_map(
            static fn (array $values): array => array_map(
                static fn (ResolvedLoaderValue $resolved): mixed => $resolved->value,
                $values
            ),
            $loaderValues
        );
    }

    /**
     * An element whose requirements resolved to nothing contributes no entry at all. Both readers of this map
     * take `$loaderValues[$id] ?? []`, so an absent entry and an empty one are the same fact to them, and the
     * map that holds only elements that actually ran a loader is the one that says so. An element whose
     * loader ran and found nothing is a different case and does get an entry: {@see ElementDataResolver}
     * returns the requirement key at `null` there, and that present null is what makes the rendered property
     * exist as null instead of being dropped.
     *
     * @param list<StoredElement> $forest
     *
     * @return array<string, array<string, ResolvedLoaderValue>> element id => requirement key => resolved value
     */
    private function resolveLoaderValues(
        array $forest,
        SalesChannelContext $context,
        Request $request,
        RenderingCacheContext $cacheContext,
    ): array {
        $values = [];

        foreach ($forest as $root) {
            $this->collectLoaderValues($root, $context, $request, $cacheContext, $values);
        }

        return $values;
    }

    /**
     * @param array<string, array<string, ResolvedLoaderValue>> $values
     */
    private function collectLoaderValues(
        StoredElement $element,
        SalesChannelContext $context,
        Request $request,
        RenderingCacheContext $cacheContext,
        array &$values,
    ): void {
        $resolved = $this->dataResolver->resolve($element, $context, $request, $cacheContext);

        if ($resolved !== []) {
            $values[$element->id] = $resolved;
        }

        foreach ($element->slots as $children) {
            foreach ($children as $child) {
                $this->collectLoaderValues($child, $context, $request, $cacheContext, $values);
            }
        }
    }
}
