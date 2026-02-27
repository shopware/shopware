<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Format;

use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\SalesChannel\AbstractContentRouteResponse;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentSkeletonRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class SkeletonResponseFactory extends AbstractResponseFactory
{
    public function getRenderingMode(): RenderingMode
    {
        return RenderingMode::SKELETON;
    }

    public function createResponse(ContentPage $contentPage): AbstractContentRouteResponse
    {
        return new ContentSkeletonRouteResponse($contentPage->getContentSkeletonPage());
    }
}
