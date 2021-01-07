<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be used to list all addresses of an customer
 */
abstract class AbstractListAddressRoute
{
    /**
     * @deprecated tag:v6.4.0 - Parameter $customer will be mandatory in future implementation
     */
    abstract public function load(Criteria $criteria, SalesChannelContext $context/*, CustomerEntity $customer*/): ListAddressRouteResponse;

    abstract public function getDecorated(): AbstractListAddressRoute;
}
