<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Output\Format;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Content\ContentSystem\RenderingMode;
use Shopware\Core\Content\ContentSystem\SalesChannel\AbstractContentRouteResponse;
use Shopware\Core\Content\ContentSystem\SalesChannel\ContentDecomposedRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class DecomposedResponseFactory extends AbstractResponseFactory
{
    public function __construct(
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider,
    ) {
    }

    /**
     * @codeCoverageIgnore
     */
    public function getRenderingMode(): RenderingMode
    {
        return RenderingMode::FULL;
    }

    public function createResponse(ContentPage $contentPage): AbstractContentRouteResponse
    {
        return new ContentDecomposedRouteResponse($contentPage->getContentDecomposedPage($this->configSerializerProvider));
    }
}
