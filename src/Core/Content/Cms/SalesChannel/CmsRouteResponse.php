<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CmsPageEntity>
 */
#[Package('discovery')]
class CmsRouteResponse extends StoreApiResponse
{
    public function getCmsPage(): CmsPageEntity
    {
        return $this->object;
    }
}
