<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel\Footer;

use Shopware\Core\Content\ContentSystem\Output\Struct\ContentDataPage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @final
 *
 * @extends StoreApiResponse<ContentDataPage>
 */
#[Package('discovery')]
class ContentFooterDataRouteResponse extends StoreApiResponse
{
    public function __construct(
        ContentDataPage $dataPage,
    ) {
        parent::__construct($dataPage);
    }

    public function getContentDataPage(): ContentDataPage
    {
        return $this->object;
    }
}
