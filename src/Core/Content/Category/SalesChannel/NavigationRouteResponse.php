<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('content')]
class NavigationRouteResponse extends StoreApiResponse
{
    /**
     * @var CategoryCollection
     */
    protected $object;

    public function __construct(CategoryCollection $categories)
    {
        parent::__construct($categories);
    }

    public function getCategories(): CategoryCollection
    {
        return $this->object;
    }
}
