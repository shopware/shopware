<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('checkout')]
class CustomerResponse extends StoreApiResponse
{
    /**
     * @var CustomerEntity
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $object;

    public function __construct(CustomerEntity $object)
    {
        parent::__construct($object);
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->object;
    }
}
