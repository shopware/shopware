<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package checkout
 */
class CartMergedEvent extends Event implements ShopwareSalesChannelEvent
{
    /**
     * @internal
     */
    public function __construct(protected Cart $cart, protected SalesChannelContext $context, protected Cart $previousCart)
    {
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getPreviousCart(): Cart
    {
        return $this->previousCart;
    }
}
