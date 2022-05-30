<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Confirm;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Storefront\Page\Page;

class CheckoutConfirmPage extends Page
{
    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var PaymentMethodCollection
     */
    protected $paymentMethods;

    /**
     * @var ShippingMethodCollection
     */
    protected $shippingMethods;

    protected array $formattingCustomerAddresses = [];

    public function getFormattingCustomerAddresses(): array
    {
        return $this->formattingCustomerAddresses;
    }

    public function setFormattingCustomerAddresses(array $formattingCustomerAddresses): ?array
    {
        return $this->formattingCustomerAddresses = $formattingCustomerAddresses;
    }

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
}
