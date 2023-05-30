<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be used to create an order from the cart
 */
#[Package('checkout')]
abstract class AbstractCartOrderRoute
{
    abstract public function getDecorated(): AbstractCartOrderRoute;

    abstract public function order(Cart $cart, SalesChannelContext $context, RequestDataBag $data): CartOrderRouteResponse;
}
