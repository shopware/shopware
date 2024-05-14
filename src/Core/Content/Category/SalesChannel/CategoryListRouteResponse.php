<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('inventory')]
class CategoryListRouteResponse extends StoreApiResponse
{
    /**
     * @var EntitySearchResult<CategoryCollection>
     */
    protected $object;

    /**
     * @param EntitySearchResult<CategoryCollection> $object
     */
    public function __construct(EntitySearchResult $object)
    {
        parent::__construct($object);
    }

    public function getCategories(): CategoryCollection
    {
        return $this->object->getEntities();
    }
}
