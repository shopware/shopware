<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Order\OrderStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Language\LanguageStruct;

class PaymentTransactionStruct extends Struct
{
    /**
     * @var string
     */
    protected $transactionId;

    /**
     * @var OrderStruct
     */
    protected $order;

    /**
     * @var Price
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

    /**
     * @var null|PaymentMethodStruct
     */
    protected $paymentMethod;

    /**
     * @var LanguageStruct|null
     */
    protected $language;

    public function __construct(
        string $transactionId,
        string $paymentMethodId,
        OrderStruct $order,
        Price $amount,
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
     * @return OrderStruct
     */
    public function getOrder(): OrderStruct
    {
        return $this->order;
    }

    /**
     * @return Price
     */
    public function getAmount(): Price
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

    public function getPaymentMethod(): ?PaymentMethodStruct
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodStruct $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getLanguage(): ?LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }
}
