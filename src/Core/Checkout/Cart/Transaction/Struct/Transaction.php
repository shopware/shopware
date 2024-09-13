<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Transaction\Struct;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class Transaction extends Struct
{
    /**
     * @var CalculatedPrice
     */
    protected $amount;

    /**
     * @var string
     */
    protected $paymentMethodId;

    protected ?Struct $validationStruct = null;

    public function __construct(
        CalculatedPrice $amount,
        string $paymentMethodId
    ) {
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

    public function getValidationStruct(): ?Struct
    {
        return $this->validationStruct;
    }

    public function setValidationStruct(?Struct $validationStruct): void
    {
        $this->validationStruct = $validationStruct;
    }

    public function getApiAlias(): string
    {
        return 'cart_transaction';
    }
}
