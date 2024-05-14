<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('checkout')]
class ListAddressRouteResponse extends StoreApiResponse
{
    /**
     * @var EntitySearchResult<CustomerAddressCollection>
     */
    protected $object;

    /**
     * @param EntitySearchResult<CustomerAddressCollection> $object
     */
    public function __construct(EntitySearchResult $object)
    {
        parent::__construct($object);
    }

    public function getAddressCollection(): CustomerAddressCollection
    {
        return $this->object->getEntities();
    }
}
