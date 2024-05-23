<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway;

use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
final class CheckoutGatewayResponse extends Struct
{
    /**
     * @internal
     */
    public function __construct(
        protected PaymentMethodCollection $availablePaymentMethods,
        protected ShippingMethodCollection $availableShippingMethods,
        protected ErrorCollection $cartErrors,
    ) {
    }

    public function getAvailablePaymentMethods(): PaymentMethodCollection
    {
        return $this->availablePaymentMethods;
    }

    public function setAvailablePaymentMethods(PaymentMethodCollection $availablePaymentMethods): void
    {
        $this->availablePaymentMethods = $availablePaymentMethods;
    }

    public function getAvailableShippingMethods(): ShippingMethodCollection
    {
        return $this->availableShippingMethods;
    }

    public function setAvailableShippingMethods(ShippingMethodCollection $availableShippingMethods): void
    {
        $this->availableShippingMethods = $availableShippingMethods;
    }

    public function getCartErrors(): ErrorCollection
    {
        return $this->cartErrors;
    }

    public function setCartErrors(ErrorCollection $cartErrors): void
    {
        $this->cartErrors = $cartErrors;
    }
}
