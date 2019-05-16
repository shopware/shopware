<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Offcanvas;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Storefront\Page\Page;

class OffcanvasCartPage extends Page
{
    /**
     * @var Cart
     */
    protected $cart;

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }
}
