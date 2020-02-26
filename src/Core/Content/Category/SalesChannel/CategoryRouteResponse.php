<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelApiResponse;

class CategoryRouteResponse extends SalesChannelApiResponse
{
    /**
     * @var CategoryEntity
     */
    protected $object;

    public function __construct(CategoryEntity $categories)
    {
        parent::__construct($categories);
    }

    public function getCategory(): CategoryEntity
    {
        return $this->object;
    }
}
