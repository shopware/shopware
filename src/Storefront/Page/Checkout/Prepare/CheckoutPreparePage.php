<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Prepare;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Page;

#[Package('storefront')]
class CheckoutPreparePage extends Page
{
    protected Cart $cart;

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }
}
