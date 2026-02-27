<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter;

use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class RenderingSpecificationFactory
{
    public function create(
        AbstractSpecificationSource $source,
        string $path,
        Request $request,
        SalesChannelContext $context,
    ): RenderingSpecification {
        $data = $source->resolveSpecificationData($path, $request, $context);

        return new RenderingSpecification(
            layoutId: $source->resolveLayoutId($path, $request, $context),
            dataRequirements: $data->dataRequirements,
            placeholderValues: $data->placeholderValues,
            request: $request,
            targetElementId: $source->resolveTargetElementId($path, $request, $context),
            cacheTags: $source->resolveCacheTags($path, $request, $context),
        );
    }
}
