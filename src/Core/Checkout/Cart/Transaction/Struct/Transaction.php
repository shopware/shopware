<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Transaction\Struct;

use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Framework\Struct\Struct;

class Transaction extends Struct
{
    /** @var Price */
    protected $amount;

    /** @var string */
    protected $paymentMethodId;

    public function __construct(Price $amount, string $paymentMethodId)
    {
        $this->amount = $amount;
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getAmount(): Price
    {
        return $this->amount;
    }

    public function setAmount(Price $amount): void
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
