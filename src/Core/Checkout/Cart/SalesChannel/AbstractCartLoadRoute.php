<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Deprecation\BCChange\NewOptionalParameter;
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

    #[NewOptionalParameter(version: 'v6.8.0', parameterName: 'cart', parameterType: '?' . Cart::class, defaultValue: null, description: 'The cart to respond with, so the route does not read and calculate a cart that the caller already holds. Stays optional because the route still reads from the cart storage when the request asks for another token.')]
    abstract public function load(
        Request $request,
        SalesChannelContext $context,
        /* , ?Cart $cart = null */
    ): CartResponse;
}
