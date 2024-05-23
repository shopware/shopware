<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\SalesChannel;

use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @method ArrayStruct getObject()
 */
#[Package('checkout')]
class CheckoutGatewayRouteResponse extends StoreApiResponse
{
    public function __construct(
        private PaymentMethodCollection $payments,
        private ShippingMethodCollection $shipments,
        private ErrorCollection $errors,
    ) {
        parent::__construct(new ArrayStruct([
            'payments' => $payments,
            'shipments' => $shipments,
            'errors' => $errors,
        ]));
    }

    public function getPaymentMethods(): PaymentMethodCollection
    {
        return $this->payments;
    }

    public function setPaymentMethods(PaymentMethodCollection $payments): void
    {
        $this->payments = $payments;
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return $this->shipments;
    }

    public function setShippingMethods(ShippingMethodCollection $shipments): void
    {
        $this->shipments = $shipments;
    }

    public function getErrors(): ErrorCollection
    {
        return $this->errors;
    }

    public function setErrors(ErrorCollection $errors): void
    {
        $this->errors = $errors;
    }
}
