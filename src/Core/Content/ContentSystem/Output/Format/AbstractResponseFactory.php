<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Output\Format;

use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Content\ContentSystem\RenderingMode;
use Shopware\Core\Content\ContentSystem\SalesChannel\AbstractContentRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
abstract class AbstractResponseFactory
{
    abstract public function getRenderingMode(): RenderingMode;

    abstract public function createResponse(ContentPage $contentPage): AbstractContentRouteResponse;
}
