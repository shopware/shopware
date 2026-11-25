<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Format;

use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentSkeletonElement;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentSkeletonPage;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\SalesChannel\AbstractContentRouteResponse;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentSkeletonRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class SkeletonResponseFactory extends AbstractResponseFactory
{
    public function getRenderingMode(): RenderingMode
    {
        return RenderingMode::SKELETON;
    }

    public function collectsValueIndex(): bool
    {
        return false;
    }

    public function createResponse(RenderResult $result): AbstractContentRouteResponse
    {
        return new ContentSkeletonRouteResponse(new ContentSkeletonPage(
            $result->reference->id,
            ContentSkeletonElement::fromRendered($result->tree),
            $result->reference->name,
            $result->reference->version,
        ));
    }
}
