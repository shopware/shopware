<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to load all shipping methods of the authenticated sales channel.
 * It is possible to use the query parameter 'onlyAvailable' to load only the available shipping methods.
 * When sending this parameter, the shipping methods are validated against the active rules.
 * With this route it is also possible to send the standard API parameters such as: 'page', 'limit', 'filter', etc.
 */
#[Package('checkout')]
abstract class AbstractShippingMethodRoute
{
    abstract public function getDecorated(): AbstractShippingMethodRoute;

    abstract public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse;
}
