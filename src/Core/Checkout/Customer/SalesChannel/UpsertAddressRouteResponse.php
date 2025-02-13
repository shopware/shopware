<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CustomerAddressEntity>
 */
#[Package('checkout')]
class UpsertAddressRouteResponse extends StoreApiResponse
{
    public function getAddress(): CustomerAddressEntity
    {
        return $this->object;
    }
}
