<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Format;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\SalesChannel\AbstractContentRouteResponse;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentDecomposedRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DecomposedResponseFactory extends AbstractResponseFactory
{
    public function __construct(
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider,
        private readonly ConfigCanonicalizer $configCanonicalizer,
    ) {
    }

    /**
     * @codeCoverageIgnore
     */
    public function getRenderingMode(): RenderingMode
    {
        return RenderingMode::FULL;
    }

    public function collectsValueIndex(): bool
    {
        return true;
    }

    public function createResponse(RenderResult $result): AbstractContentRouteResponse
    {
        return new ContentDecomposedRouteResponse($result->page->getContentDecomposedPage($this->configSerializerProvider, $this->configCanonicalizer));
    }
}
