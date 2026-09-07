<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Rendering;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
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

        $ambientValues = array_map(static fn (ResolvedLoaderValue $resolved): mixed => $resolved->value, $ambient);
        $loaderValues = [];

        if ($virtualRoot !== null && $ambient !== []) {
            $loaderValues[$virtualRoot->id] = $ambient;
        }

        foreach ($forest as $root) {
            $this->resolveLoaderValues($root, $context, $request, $cacheContext, $ambientValues, $loaderValues);
        }

        $deliveries = $this->deliveryResolver->resolve(
            $forest,
            $this->plainValues($loaderValues),
            $ambientValues,
        );

        return $this->treeFactory->create($forest, $deliveries, $loaderValues, $mode);
    }

    /**
     * @param list<DataRequirement> $requirements
     *
     * @return array<string, DataRequirement>
     */
    private function indexByRequirementKey(array $requirements): array
    {
        $indexed = [];
        foreach ($requirements as $requirement) {
            $indexed[$requirement->key] = $requirement;
        }

        return $indexed;
    }

    /**
     * Root-scoped context is independent of the tree's provider chain and is therefore available while data
     * loaders are resolved. Parent-scoped context is deliberately delivered only after every loader has run.
     *
     * @param array<string, mixed> $ambientValues
     * @param array<string, array<string, ResolvedLoaderValue>> $loaderValues
     */
    private function resolveLoaderValues(
        StoredElement $element,
        SalesChannelContext $context,
        Request $request,
        RenderingCacheContext $cacheContext,
        array $ambientValues,
        array &$loaderValues,
    ): void {
        $rootContext = $this->deliveryResolver->resolveRootContext(
            $element,
            $ambientValues,
            new ContextDelivery($element->id),
        );
        $resolved = $this->dataResolver->resolve($element, $context, $request, $cacheContext, $rootContext->context);

        if ($resolved !== []) {
            $loaderValues[$element->id] = $resolved;
        }

        foreach ($element->slots as $slotChildren) {
            foreach ($slotChildren as $child) {
                $this->resolveLoaderValues(
                    $child,
                    $context,
                    $request,
                    $cacheContext,
                    $ambientValues,
                    $loaderValues,
                );
            }
        }
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
                static fn (ResolvedLoaderValue $value): mixed => $value->value,
                $values,
            ),
            $loaderValues,
        );
    }
}
