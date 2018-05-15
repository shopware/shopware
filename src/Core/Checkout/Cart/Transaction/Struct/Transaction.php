<?php declare(strict_types=1);

namespace Shopware\Checkout\Cart\Transaction\Struct;

use Shopware\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Framework\Struct\Struct;

class Transaction extends Struct
{
    /** @var CalculatedPrice */
    protected $amount;

    /** @var string */
    protected $paymentMethodId;

    public function __construct(CalculatedPrice $amount, string $paymentMethodId)
    {
        $this->amount = $amount;
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getAmount(): CalculatedPrice
    {
        return $this->amount;
    }

    public function setAmount(CalculatedPrice $amount): void
    {
        $this->amount = $amount;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }
}
