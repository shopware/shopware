<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface ShippingMethodRouteInterface
{
    public function load(Request $request, SalesChannelContext $context): ShippingMethodRouteResponse;
}
