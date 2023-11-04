<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('content')]
class CategoryRouteResponse extends StoreApiResponse
{
    /**
     * @var CategoryEntity
     */
    protected $object;

    public function __construct(CategoryEntity $category)
    {
        parent::__construct($category);
    }

    public function getCategory(): CategoryEntity
    {
        return $this->object;
    }
}
