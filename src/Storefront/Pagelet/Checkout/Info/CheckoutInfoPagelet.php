<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Checkout\Info;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Struct\Struct;

class CheckoutInfoPagelet extends Struct
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

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }
}
