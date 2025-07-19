<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SalesChannel;

use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<SeoUrlCollection>>
 */
#[Package('inventory')]
class SeoUrlRouteResponse extends StoreApiResponse
{
    public function getSeoUrls(): SeoUrlCollection
    {
        return $this->object->getEntities();
    }
}
