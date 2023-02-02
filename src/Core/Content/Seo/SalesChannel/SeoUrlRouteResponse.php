<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SalesChannel;

use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class SeoUrlRouteResponse extends StoreApiResponse
{
    /**
     * @var EntitySearchResult
     */
    protected $object;

    public function __construct(EntitySearchResult $object)
    {
        parent::__construct($object);
    }

    public function getSeoUrls(): SeoUrlCollection
    {
        /** @var SeoUrlCollection $collection */
        $collection = $this->object->getEntities();

        return $collection;
    }
}
