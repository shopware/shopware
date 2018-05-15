<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Struct;

use Shopware\Checkout\Order\Struct\OrderDetailStruct;
use Shopware\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Framework\Struct\Struct;

class PaymentTransaction extends Struct
{
    /**
     * @var string
     */
    protected $transactionId;

    /**
     * @var OrderDetailStruct
     */
    protected $order;

    /**
     * @var CalculatedPrice
     */
    protected $amount;

    /**
     * @var string
     */
    protected $returnUrl;

    /**
     * @var string
     */
    protected $paymentMethodId;

    public function __construct(
        string $transactionId,
        string $paymentMethodId,
        OrderDetailStruct $order,
        CalculatedPrice $amount,
        string $returnUrl
    ) {
        $this->transactionId = $transactionId;
        $this->order = $order;
        $this->amount = $amount;
        $this->returnUrl = $returnUrl;
        $this->paymentMethodId = $paymentMethodId;
    }

    /**
     * @return string
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * @return OrderDetailStruct
     */
    public function getOrder(): OrderDetailStruct
    {
        return $this->order;
    }

    /**
     * @return CalculatedPrice
     */
    public function getAmount(): CalculatedPrice
    {
        return $this->amount;
    }

    /**
     * @return string
     */
    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }
}
