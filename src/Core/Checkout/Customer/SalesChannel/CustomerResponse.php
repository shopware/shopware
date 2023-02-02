<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class CustomerResponse extends StoreApiResponse
{
    /**
     * @var CustomerEntity
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
