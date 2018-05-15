<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Struct;

use Shopware\Api\Payment\Struct\PaymentMethodBasicStruct;

class OrderTransactionDetailStruct extends OrderTransactionBasicStruct
{
    /**
     * @var PaymentMethodBasicStruct
     */
    protected $paymentMethod;

    public function getPaymentMethod(): PaymentMethodBasicStruct
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodBasicStruct $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }
}
