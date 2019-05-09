<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Storefront\Page\Page;

class CheckoutCartPage extends Page
{
    /**
     * @var Cart
     */
    protected $cart;
    /** @var CountryCollection|null */
    private $shippingCountries = null;

    /** @var PaymentMethodCollection|null */
    private $paymentMethods = null;

    /** @var ShippingMethodCollection|null */
    private $shippingMethods = null;

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }

    public function setShippingCountries(?CountryCollection $countries)
    {
        $this->shippingCountries = $countries;
    }

    public function getShippingCountries(): ?CountryCollection
    {
        return $this->shippingCountries;
    }

    public function setShippingMethods(ShippingMethodCollection $shippings)
    {
        $this->shippingMethods = $shippings;
    }

    public function getShippingMethods(): ?ShippingMethodCollection
    {
        return $this->shippingMethods;
    }

    public function setPaymentMethods(PaymentMethodCollection $methods)
    {
        $this->paymentMethods = $methods;
    }

    public function getPaymentMethods(): ?PaymentMethodCollection
    {
        return $this->paymentMethods;
    }
}
