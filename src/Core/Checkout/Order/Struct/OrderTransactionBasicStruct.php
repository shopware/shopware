<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Struct;

use Shopware\Framework\ORM\Entity;
use Shopware\Cart\Price\Struct\CalculatedPrice;

class OrderTransactionBasicStruct extends Entity
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
     * @var CalculatedPrice
     */
    protected $amount;

    /**
     * @var string
     */
    protected $payload;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

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

    public function getAmount(): CalculatedPrice
    {
        return $this->amount;
    }

    public function setAmount(CalculatedPrice $amount): void
    {
        $this->amount = $amount;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): void
    {
        $this->payload = $payload;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
