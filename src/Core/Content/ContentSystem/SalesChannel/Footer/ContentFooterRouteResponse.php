<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel\Footer;

use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @final
 *
 * @extends StoreApiResponse<ContentPage>
 */
#[Package('discovery')]
class ContentFooterRouteResponse extends StoreApiResponse
{
    public function __construct(
        ContentPage $contentPage,
    ) {
        parent::__construct($contentPage);
    }

    public function getContentPage(): ContentPage
    {
        return $this->object;
    }
}
