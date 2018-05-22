<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderTransaction\Struct;

use Shopware\Checkout\Payment\Struct\PaymentMethodBasicStruct;

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
