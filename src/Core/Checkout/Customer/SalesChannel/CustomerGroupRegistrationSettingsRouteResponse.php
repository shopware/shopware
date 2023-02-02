<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('customer-order')]
class CustomerGroupRegistrationSettingsRouteResponse extends StoreApiResponse
{
    /**
     * @var CustomerGroupEntity
     */
    protected $object;

    public function __construct(CustomerGroupEntity $object)
    {
        parent::__construct($object);
    }

    public function getRegistration(): CustomerGroupEntity
    {
        return $this->object;
    }
}
