<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('buyers-experience')]
class CountryStateRouteResponse extends StoreApiResponse
{
    /**
     * @var EntitySearchResult<CountryStateCollection>
     */
    protected $object;

    /**
     * @param EntitySearchResult<CountryStateCollection> $object
     */
    public function __construct(EntitySearchResult $object)
    {
        parent::__construct($object);
    }

    public function getStates(): CountryStateCollection
    {
        return $this->object->getEntities();
    }
}
