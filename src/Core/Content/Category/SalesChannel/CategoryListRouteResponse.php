<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<CategoryCollection>>
 */
#[Package('discovery')]
class CategoryListRouteResponse extends StoreApiResponse
{
    public function getCategories(): CategoryCollection
    {
        return $this->object->getEntities();
    }
}
