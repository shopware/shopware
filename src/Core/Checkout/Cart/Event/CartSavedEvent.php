<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.4.0 - Will implement Shopware\Core\Framework\Event\ShopwareSalesChannelEvent
 */
class CartSavedEvent extends Event /*implements ShopwareSalesChannelEvent*/
{
    /**
     * @var SalesChannelContext
     */
    protected $context;

    /**
     * @var Cart
     */
    protected $cart;

    public function __construct(SalesChannelContext $context, Cart $cart)
    {
        $this->context = $context;
        $this->cart = $cart;
    }

    /**
     * @deprecated tag:v6.4.0 - Will return Shopware\Core\Framework\Context instead
     */
    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}
