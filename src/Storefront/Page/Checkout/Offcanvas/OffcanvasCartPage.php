<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Offcanvas;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Page;

#[Package('framework')]
class OffcanvasCartPage extends Page
{
    protected Cart $cart;

    protected ShippingMethodCollection $shippingMethods;

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }

    public function setShippingMethods(ShippingMethodCollection $shippingMethods): void
    {
        $this->shippingMethods = $shippingMethods;
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return $this->shippingMethods;
    }
}
