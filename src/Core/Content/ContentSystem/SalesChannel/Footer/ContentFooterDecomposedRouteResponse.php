<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel\Footer;

use Shopware\Core\Content\ContentSystem\Output\Struct\ContentDecomposedPage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @final
 *
 * @extends StoreApiResponse<ContentDecomposedPage>
 */
#[Package('discovery')]
class ContentFooterDecomposedRouteResponse extends StoreApiResponse
{
    public function __construct(
        ContentDecomposedPage $contentDecomposedPage,
    ) {
        parent::__construct($contentDecomposedPage);
    }

    public function getContentDecomposedPage(): ContentDecomposedPage
    {
        return $this->object;
    }
}
