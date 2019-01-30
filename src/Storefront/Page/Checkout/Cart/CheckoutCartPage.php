<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Storefront\Framework\Page\PageWithHeader;

class CheckoutCartPage extends PageWithHeader
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
