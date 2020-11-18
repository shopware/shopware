<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be used to create an order from the cart
 */
abstract class AbstractCartOrderRoute
{
    abstract public function getDecorated(): AbstractCartOrderRoute;

    /**
     * @deprecated tag:v6.4.0 - Parameter $data will be mandatory in future implementation
     */
    abstract public function order(Cart $cart, SalesChannelContext $context, ?RequestDataBag $data = null): CartOrderRouteResponse;
}
