<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransaction;

use DateTime;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\OrderTransactionStateStruct;
use Shopware\Core\Checkout\Order\OrderStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class OrderTransactionStruct extends Entity
{
    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var string
     */
    protected $paymentMethodId;

    /**
     * @var string
     */
    protected $orderTransactionStateId;

    /**
     * @var Price
     */
    protected $amount;

    /**
     * @var DateTime|null
     */
    protected $createdAt;

    /**
     * @var DateTime|null
     */
    protected $updatedAt;

    /**
     * @var PaymentMethodStruct|null
     */
    protected $paymentMethod;

    /**
     * @var OrderStruct|null
     */
    protected $order;

    /**
     * @var array|null
     */
    protected $details;

    /***
     * @var OrderTransactionStateStruct
     */
    protected $orderTransactionState;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getOrderTransactionStateId(): string
    {
        return $this->orderTransactionStateId;
    }

    public function setOrderTransactionStateId(string $orderTransactionStateId): void
    {
        $this->orderTransactionStateId = $orderTransactionStateId;
    }

    public function getAmount(): Price
    {
        return $this->amount;
    }

    public function setAmount(Price $amount): void
    {
        $this->amount = $amount;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function setDetails(array $details): void
    {
        $this->details = $details;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getPaymentMethod(): ?PaymentMethodStruct
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodStruct $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getOrderTransactionState(): OrderTransactionStateStruct
    {
        return $this->orderTransactionState;
    }

    public function setOrderTransactionState(OrderTransactionStateStruct $orderTransactionState): void
    {
        $this->orderTransactionState = $orderTransactionState;
    }

    public function getOrder(): ?OrderStruct
    {
        return $this->order;
    }

    public function setOrder(OrderStruct $order): void
    {
        $this->order = $order;
    }
}
