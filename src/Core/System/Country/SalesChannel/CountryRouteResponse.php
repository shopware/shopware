<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<CountryCollection>>
 */
#[Package('fundamentals@discovery')]
class CountryRouteResponse extends StoreApiResponse
{
    /**
     * @return EntitySearchResult<CountryCollection>
     */
    public function getResult(): EntitySearchResult
    {
        return $this->object;
    }

    public function getCountries(): CountryCollection
    {
        return $this->object->getEntities();
    }
}
