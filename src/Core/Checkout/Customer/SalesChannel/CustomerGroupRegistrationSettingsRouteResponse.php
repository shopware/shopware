<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CustomerGroupEntity>
 */
#[Package('checkout')]
class CustomerGroupRegistrationSettingsRouteResponse extends StoreApiResponse
{
    public function getRegistration(): CustomerGroupEntity
    {
        return $this->object;
    }
}
