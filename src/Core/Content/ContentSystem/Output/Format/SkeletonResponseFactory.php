<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Output\Format;

use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Content\ContentSystem\RenderingMode;
use Shopware\Core\Content\ContentSystem\SalesChannel\AbstractContentRouteResponse;
use Shopware\Core\Content\ContentSystem\SalesChannel\ContentSkeletonRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
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
