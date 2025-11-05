<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\Output\Struct\DecomposedContentPage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @final
 *
 * @extends StoreApiResponse<DecomposedContentPage>
 */
#[Package('discovery')]
class ContentDecomposedRouteResponse extends StoreApiResponse
{
    public function __construct(
        DecomposedContentPage $decomposedContentPage,
    ) {
        parent::__construct($decomposedContentPage);
    }

    public function getDecomposedContentPage(): DecomposedContentPage
    {
        return $this->object;
    }
}
