<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter;

use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\ContentSystem\ResolvedContentLayout;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class RenderingSpecificationFactory
{
    public function create(
        AbstractSpecificationSource $source,
        string $path,
        Request $request,
        SalesChannelContext $context,
    ): ResolvedContentLayout {
        $layoutId = $source->resolveLayoutId($path, $request, $context);
        $data = $source->resolveSpecificationData($path, $request, $context);

        return ResolvedContentLayout::create(
            $layoutId,
            new RenderingSpecification(
                dataRequirements: $data->dataRequirements,
                placeholderValues: $data->placeholderValues,
                request: $request,
                targetElementId: $source->resolveTargetElementId($path, $request, $context),
                cacheTags: $source->resolveCacheTags($path, $request, $context),
            ),
        );
    }

    public function createWithoutLayout(
        AbstractSpecificationSource $source,
        string $entityId,
        Request $request,
        SalesChannelContext $context,
    ): RenderingSpecification {
        $data = $source->resolveSpecificationDataForEntity($entityId, $request, $context);

        return new RenderingSpecification(
            dataRequirements: $data->dataRequirements,
            placeholderValues: $data->placeholderValues,
            request: $request,
            targetElementId: $source->resolveTargetElementId('', $request, $context),
            cacheTags: [],
        );
    }
}
