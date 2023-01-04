<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('checkout')]
abstract class AbstractShippingMethodRoute
{
    abstract public function getDecorated(): AbstractShippingMethodRoute;

    abstract public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse;
}
