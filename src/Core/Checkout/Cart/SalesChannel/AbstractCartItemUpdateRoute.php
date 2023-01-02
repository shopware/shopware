<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('checkout')]
abstract class AbstractCartItemUpdateRoute
{
    abstract public function getDecorated(): AbstractCartItemUpdateRoute;

    abstract public function change(Request $request, Cart $cart, SalesChannelContext $context): CartResponse;
}
