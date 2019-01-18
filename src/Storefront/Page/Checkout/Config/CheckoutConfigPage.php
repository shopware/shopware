<?php

namespace Shopware\Storefront\Page\Checkout\Config;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Storefront\Framework\Page\GenericPage;

class CheckoutConfigPage extends GenericPage
{
    /**
     * @var PaymentMethodCollection
     */
    protected $paymentMethods;

    /**
     * @var ShippingMethodCollection
     */
    protected $shippingMethods;

    /**
     * @var PaymentMethodEntity
     */
    protected $activePaymentMethod;

    /**
     * @var ShippingMethodEntity
     */
    protected $activeShippingMethod;

    public function __construct(
        PaymentMethodCollection $paymentMethods,
        ShippingMethodCollection $shippingMethods,
        PaymentMethodEntity $activePaymentMethod,
        ShippingMethodEntity $activeShippingMethod
    ) {
        $this->paymentMethods = $paymentMethods;
        $this->shippingMethods = $shippingMethods;
        $this->activePaymentMethod = $activePaymentMethod;
        $this->activeShippingMethod = $activeShippingMethod;
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

    public function getActivePaymentMethod(): PaymentMethodEntity
    {
        return $this->activePaymentMethod;
    }

    public function setActivePaymentMethod(PaymentMethodEntity $activePaymentMethod): void
    {
        $this->activePaymentMethod = $activePaymentMethod;
    }

    public function getActiveShippingMethod(): ShippingMethodEntity
    {
        return $this->activeShippingMethod;
    }

    public function setActiveShippingMethod(ShippingMethodEntity $activeShippingMethod): void
    {
        $this->activeShippingMethod = $activeShippingMethod;
    }
}