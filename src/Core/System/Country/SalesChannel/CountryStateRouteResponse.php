<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('system-settings')]
class CountryStateRouteResponse extends StoreApiResponse
{
    /**
     * @var EntitySearchResult
     */
    protected $object;

    public function __construct(EntitySearchResult $object)
    {
        parent::__construct($object);
    }

    public function getStates(): CountryStateCollection
    {
        /** @var CountryStateCollection $countryStateCollection */
        $countryStateCollection = $this->object->getEntities();

        return $countryStateCollection;
    }
}
