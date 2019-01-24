<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Language\LanguageEntity;

class PaymentTransactionStruct extends Struct
{
    /**
     * @var string
     */
    protected $transactionId;

    /**
     * @var OrderEntity
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

    /**
     * @var PaymentMethodEntity|null
     */
    protected $paymentMethod;

    /**
     * @var LanguageEntity|null
     */
    protected $language;

    public function __construct(
        string $transactionId,
        string $paymentMethodId,
        OrderEntity $order,
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
     * @return OrderEntity
     */
    public function getOrder(): OrderEntity
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

    public function getPaymentMethod(): ?PaymentMethodEntity
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodEntity $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }
}
