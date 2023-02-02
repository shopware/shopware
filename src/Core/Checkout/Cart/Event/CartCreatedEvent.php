<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Symfony\Contracts\EventDispatcher\Event;

class CartCreatedEvent extends Event
{
    /**
     * @var Cart
     */
    protected $cart;

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}
