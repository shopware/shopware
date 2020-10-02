<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class CartSavedEvent extends Event
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

    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}
