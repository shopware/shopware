<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\SalesChannel\Struct\ContentPage;
use Shopware\Core\Content\ContentSystem\SalesChannel\Struct\DecomposedContentPage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @final
 *
 * @extends StoreApiResponse<DecomposedContentPage>
 */
#[Package('discovery')]
class ContentRouteResponse extends StoreApiResponse
{
    public function __construct(
        public readonly ContentPage $contentPage,
    ) {
        parent::__construct($this->contentPage->getDecomposedContentPage());
    }

    public function getDecomposedContentPage(): DecomposedContentPage
    {
        return $this->object;
    }
}
