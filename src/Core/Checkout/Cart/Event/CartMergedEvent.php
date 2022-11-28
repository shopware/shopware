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
    protected Cart $cart;

    protected SalesChannelContext $context;

    protected Cart $previousCart;

    /**
     * @internal
     */
    public function __construct(Cart $cart, SalesChannelContext $context, Cart $previousCart)
    {
        $this->cart = $cart;
        $this->context = $context;
        $this->previousCart = $previousCart;
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
