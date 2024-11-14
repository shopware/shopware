<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Storefront\Page\Page;

#[Package('storefront')]
class CheckoutCartPage extends Page
{
    /**
     * @var Cart
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $cart;

    /**
     * @var CountryCollection
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $countries;

    /**
     * @var PaymentMethodCollection
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $paymentMethods;

    /**
     * @var ShippingMethodCollection
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $shippingMethods;

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }

    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    public function setPaymentMethods(PaymentMethodCollection $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    public function getPaymentMethods(): PaymentMethodCollection
    {
        return $this->paymentMethods;
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
