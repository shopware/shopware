<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Confirm;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Page\Page;

#[Package('storefront')]
class CheckoutConfirmPage extends Page
{
    protected Cart $cart;

    protected PaymentMethodCollection $paymentMethods;

    protected ShippingMethodCollection $shippingMethods;

    protected bool $showRevocation = false;

    protected bool $hideShippingAddress = false;

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }

    public function getPaymentMethods(): PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(PaymentMethodCollection $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return $this->shippingMethods;
    }

    public function setShippingMethods(ShippingMethodCollection $shippingMethods): void
    {
        $this->shippingMethods = $shippingMethods;
    }

    public function isShowRevocation(): bool
    {
        return $this->showRevocation;
    }

    public function setShowRevocation(bool $showRevocation): void
    {
        $this->showRevocation = $showRevocation;
    }

    public function isHideShippingAddress(): bool
    {
        return $this->hideShippingAddress;
    }

    public function setHideShippingAddress(bool $hideShippingAddress): void
    {
        $this->hideShippingAddress = $hideShippingAddress;
    }
}
