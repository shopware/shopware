<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SalesChannel;

use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('buyers-experience')]
class SeoUrlRouteResponse extends StoreApiResponse
{
    /**
     * @var EntitySearchResult<SeoUrlCollection>
     */
    protected $object;

    /**
     * @param EntitySearchResult<SeoUrlCollection> $object
     */
    public function __construct(EntitySearchResult $object)
    {
        parent::__construct($object);
    }

    public function getSeoUrls(): SeoUrlCollection
    {
        return $this->object->getEntities();
    }
}
