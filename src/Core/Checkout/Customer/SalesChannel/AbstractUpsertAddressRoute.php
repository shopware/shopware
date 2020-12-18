<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be used to create or update new customer addresses
 */
abstract class AbstractUpsertAddressRoute
{
    /**
     * @deprecated tag:v6.4.0 - Parameter $customer will be mandatory in future implementation
     */
    abstract public function upsert(?string $addressId, RequestDataBag $data, SalesChannelContext $context/*, CustomerEntity $customer*/): UpsertAddressRouteResponse;

    abstract public function getDecorated(): AbstractUpsertAddressRoute;
}
