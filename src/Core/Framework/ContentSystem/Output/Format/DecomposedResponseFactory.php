<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Format;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\SalesChannel\AbstractContentRouteResponse;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentDecomposedRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
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
