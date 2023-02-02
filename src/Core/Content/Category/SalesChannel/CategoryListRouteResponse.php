<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class CategoryListRouteResponse extends StoreApiResponse
{
    /**
     * @var EntitySearchResult
     */
    protected $object;

    public function __construct(EntitySearchResult $object)
    {
        parent::__construct($object);
    }

    public function getCategories(): CategoryCollection
    {
        /** @var CategoryCollection $categories */
        $categories = $this->object->getEntities();

        return $categories;
    }
}
