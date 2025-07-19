<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CategoryEntity>
 */
#[Package('discovery')]
class CategoryRouteResponse extends StoreApiResponse
{
    public function getCategory(): CategoryEntity
    {
        return $this->object;
    }
}
