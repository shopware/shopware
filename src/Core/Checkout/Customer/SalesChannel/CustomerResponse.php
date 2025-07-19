<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CustomerEntity>
 */
#[Package('checkout')]
class CustomerResponse extends StoreApiResponse
{
    public function getCustomer(): CustomerEntity
    {
        return $this->object;
    }
}
