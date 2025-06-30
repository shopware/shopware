<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<PartialEntity|CustomerEntity>
 */
#[Package('checkout')]
class CustomerResponse extends StoreApiResponse
{
    /**
     * If the criteria used to load the customer results in a partial entity,
     * the customer entity returned may be incomplete.
     * Use {@see CustomerResponse::getPartialCustomer} to check for a partial entity.
     */
    public function getCustomer(): CustomerEntity
    {
        if ($this->object instanceof PartialEntity) {
            return (new CustomerEntity())->assign($this->object->all());
        }

        return $this->object;
    }

    public function getPartialCustomer(): ?PartialEntity
    {
        return $this->object instanceof PartialEntity ? $this->object : null;
    }
}
