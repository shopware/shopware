<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class CartCreatedEvent extends Event implements CartEvent
{
    public function __construct(
        protected Cart $cart
    ) {
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}
