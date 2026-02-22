<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\Output\Struct\ContentDecomposedPage;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('discovery')]
class ContentDecomposedRouteResponse extends AbstractContentRouteResponse
{
    private readonly ContentDecomposedPage $contentDecomposedPage;

    /**
     * @codeCoverageIgnore
     */
    public function __construct(
        ContentDecomposedPage $contentDecomposedPage,
    ) {
        parent::__construct($contentDecomposedPage);
        $this->contentDecomposedPage = $contentDecomposedPage;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getContentDecomposedPage(): ContentDecomposedPage
    {
        return $this->contentDecomposedPage;
    }
}
