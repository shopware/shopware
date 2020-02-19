<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelApiResponse;

class CategoryResponse extends SalesChannelApiResponse
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
