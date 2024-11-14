<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('checkout')]
class UpsertAddressRouteResponse extends StoreApiResponse
{
    /**
     * @var CustomerAddressEntity
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
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
