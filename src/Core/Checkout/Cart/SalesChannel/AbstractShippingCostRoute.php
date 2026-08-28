<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractShippingCostRoute
{
    abstract public function getDecorated(): AbstractShippingCostRoute;

    abstract public function shippingCostsCart(Cart $cart, SalesChannelContext $salesChannelContext): ShippingCostRouteResponse;
}
