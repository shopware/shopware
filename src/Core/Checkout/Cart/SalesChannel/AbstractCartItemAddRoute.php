<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to add new line items to the cart
 */
#[Package('checkout')]
abstract class AbstractCartItemAddRoute
{
    abstract public function getDecorated(): AbstractCartItemAddRoute;

    abstract public function add(Request $request, Cart $cart, SalesChannelContext $context, ?array $items): CartResponse;
}
