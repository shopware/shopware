<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Deprecation\BCChange\NewRequiredParameter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to load the cart
 */
#[Package('checkout')]
abstract class AbstractCartLoadRoute
{
    abstract public function getDecorated(): AbstractCartLoadRoute;

    #[NewRequiredParameter(version: 'v6.8.0', parameterName: 'cart', parameterType: Cart::class, description: 'The cart to respond with, so the route no longer reads and calculates a cart that the caller already holds.')]
    abstract public function load(
        Request $request,
        SalesChannelContext $context,
        /* , Cart $cart */
    ): CartResponse;
}
