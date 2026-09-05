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
 * {@see ContextDeliveryResolver} answers what one loaded element delivers to its direct children,
 * {@see RenderedTreeFactory} mints the tree — and this is the one place that owns the step as a whole. The
 * forest-wide data and delivery walk lives here because both resolvers see one element at a time.
 *
 * Ordering is the substance of this class. In FULL mode an element's data loads before its context is
 * distributed to direct children, and a child loads only after receiving that context. This preserves the
 * data dependency represented by a parent provider and child consumer.
 *
 * The walk is pre-order and descends slot by slot: an element loads and distributes before the elements under
 * it, and each slot's children follow declaration order. Besides making the parent-to-child data dependency
 * possible, this fixes the order of loader side effects and the cache tag list's first occurrences. The cache
 * disabled state and the tag set remain order-independent because disabling is irreversible and
 * {@see RenderingCacheContext::addTags()} deduplicates tags.
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

        $loaderValues = [];
        $deliveries = [];

        foreach ($forest as $root) {
            $this->lowerElement($root, $context, $request, $cacheContext, $loaderValues, $deliveries, new ContextDelivery($root->id));
        }

        $deliveries = new ContextDeliveryIndex($deliveries);

        return $this->treeFactory->create($forest, $deliveries, $loaderValues, $mode);
    }

    /**
     * @param array<string, array<string, ResolvedLoaderValue>> $loaderValues
     * @param array<string, ContextDelivery> $deliveries
     */
    private function lowerElement(
        StoredElement $element,
        SalesChannelContext $context,
        Request $request,
        RenderingCacheContext $cacheContext,
        array &$loaderValues,
        array &$deliveries,
        ContextDelivery $delivery,
    ): void {
        $deliveries[$element->id] = $delivery;
        $resolved = $this->dataResolver->resolve($element, $context, $request, $cacheContext, $delivery->context);

        if ($resolved !== []) {
            $loaderValues[$element->id] = $resolved;
        }

        $plainValues = [];
        foreach ($resolved as $key => $value) {
            $plainValues[$key] = $value->value;
        }

        $childDeliveries = $this->deliveryResolver->resolveDirectChildren($element, $plainValues, $delivery->context);
        $childIndex = 0;

        foreach ($element->slots as $slotChildren) {
            foreach ($slotChildren as $child) {
                $this->lowerElement($child, $context, $request, $cacheContext, $loaderValues, $deliveries, $childDeliveries[$childIndex] ?? new ContextDelivery($child->id));
                ++$childIndex;
            }
        }
    }
}
