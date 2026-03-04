<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\SalesChannel;

use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentDecomposedPage;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class ContentDecomposedRouteResponse extends AbstractContentRouteResponse
{
    private readonly ContentDecomposedPage $contentDecomposedPage;

    public function __construct(
        ContentDecomposedPage $contentDecomposedPage,
    ) {
        parent::__construct($contentDecomposedPage);
        $this->contentDecomposedPage = $contentDecomposedPage;
    }

    public function getContentDecomposedPage(): ContentDecomposedPage
    {
        return $this->contentDecomposedPage;
    }
}
