<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be used to list all addresses of an customer
 */
abstract class AbstractListAddressRoute
{
    abstract public function load(Criteria $criteria, SalesChannelContext $context): ListAddressRouteResponse;

    abstract public function getDecorated(): AbstractListAddressRoute;
}
