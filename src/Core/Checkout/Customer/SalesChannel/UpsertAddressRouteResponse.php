<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class UpsertAddressRouteResponse extends StoreApiResponse
{
    /**
     * @var CustomerAddressEntity
     */
    protected $object;

    public function __construct(CustomerAddressEntity $address)
    {
        parent::__construct($address);
    }

    public function getAddress(): CustomerAddressEntity
    {
        return $this->object;
    }
}
