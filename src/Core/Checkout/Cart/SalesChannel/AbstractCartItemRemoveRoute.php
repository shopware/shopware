<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to remove line items from cart
 */
#[Package('checkout')]
abstract class AbstractCartItemRemoveRoute
{
    abstract public function getDecorated(): AbstractCartItemRemoveRoute;

    abstract public function remove(Request $request, Cart $cart, SalesChannelContext $context): CartResponse;
}
