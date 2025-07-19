<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<CountryStateCollection>>
 */
#[Package('fundamentals@discovery')]
class CountryStateRouteResponse extends StoreApiResponse
{
    public function getStates(): CountryStateCollection
    {
        return $this->object->getEntities();
    }
}
