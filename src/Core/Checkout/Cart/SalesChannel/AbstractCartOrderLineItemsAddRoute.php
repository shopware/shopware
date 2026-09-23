<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route adds the product line items of an existing order to the current cart
 */
#[Package('checkout')]
abstract class AbstractCartOrderLineItemsAddRoute
{
    abstract public function getDecorated(): AbstractCartOrderLineItemsAddRoute;

    abstract public function add(string $orderId, Request $request, Cart $cart, SalesChannelContext $context): CartResponse;
}
