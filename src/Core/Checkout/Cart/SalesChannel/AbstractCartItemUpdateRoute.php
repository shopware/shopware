<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * The 'AbstractCartItemUpdateRoute' is responsible for updating the data of a line item.
 * Internally the LineItemFactory is addressed for this purpose, where each line item type is handled individually.
 * After the line item has been updated, the cart is recalculated, then saved under the current token and returned calculated.
 */
#[Package('checkout')]
abstract class AbstractCartItemUpdateRoute
{
    abstract public function getDecorated(): AbstractCartItemUpdateRoute;

    abstract public function change(Request $request, Cart $cart, SalesChannelContext $context): CartResponse;
}
