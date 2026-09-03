<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Turns one stored forest into the rendered forest it serves as.
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
     * @param list<StoredElement> $forest roots in order
     * @param list<DataRequirement> $pageDataRequirements page-level requirements
     * @param StoredElement|null $virtualRoot wrapper for page-level requirements
     */
    public function lower(
        array $forest,
        RenderingMode $mode,
        SalesChannelContext $context,
        Request $request,
        RenderingCacheContext $cacheContext,
        array $pageDataRequirements = [],
        ?StoredElement $virtualRoot = null,
    ): LoweringResult {
        if ($mode === RenderingMode::SKELETON) {
            return $this->treeFactory->create($forest, new ContextDeliveryIndex(), [], $mode);
        }

        $ambient = [];
        if ($virtualRoot !== null && $pageDataRequirements !== []) {
            $ambient = $this->dataResolver->resolveRequirements(
                $virtualRoot,
                $this->indexByRequirementKey($pageDataRequirements),
                $context,
                $request,
                $cacheContext,
            );
        }

        $loaderValues = [];
        $deliveries = [];
        $ambientValues = array_map(static fn (ResolvedLoaderValue $resolved): mixed => $resolved->value, $ambient);

        foreach ($forest as $root) {
            $rootDelivery = $this->deliveryResolver->resolveRootContext(
                $root,
                $ambientValues,
                new ContextDelivery($root->id),
            );
            $this->lowerElement($root, $context, $request, $cacheContext, $loaderValues, $deliveries, $rootDelivery, $ambientValues);
        }

        if ($virtualRoot !== null && $ambient !== []) {
            $loaderValues[$virtualRoot->id] = $ambient;
        }

        return $this->treeFactory->create($forest, new ContextDeliveryIndex($deliveries), $loaderValues, $mode);
    }

    /** @param list<DataRequirement> $requirements @return array<string, DataRequirement> */
    private function indexByRequirementKey(array $requirements): array
    {
        $indexed = [];
        foreach ($requirements as $requirement) {
            $indexed[$requirement->key] = $requirement;
        }

        return $indexed;
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
        array $ambientValues,
    ): void {
        $deliveries[$element->id] = $delivery;
        $resolved = $this->dataResolver->resolve($element, $context, $request, $cacheContext, $delivery->context);

        if ($resolved !== []) {
            $loaderValues[$element->id] = $resolved;
        }

        $plainValues = array_map(static fn (ResolvedLoaderValue $value): mixed => $value->value, $resolved);
        $childDeliveries = $this->deliveryResolver->resolveDirectChildren(
            $element,
            $plainValues,
            $delivery->context,
        );
        $childIndex = 0;

        foreach ($element->slots as $slotChildren) {
            foreach ($slotChildren as $child) {
                $this->lowerElement(
                    $child,
                    $context,
                    $request,
                    $cacheContext,
                    $loaderValues,
                    $deliveries,
                    $this->deliveryResolver->resolveRootContext(
                        $child,
                        $ambientValues,
                        $childDeliveries[$childIndex] ?? new ContextDelivery($child->id),
                    ),
                    $ambientValues,
                );
                ++$childIndex;
            }
        }
    }
}
