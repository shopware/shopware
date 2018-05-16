<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderDeliveryPosition\Struct;

use Shopware\Checkout\Order\Aggregate\OrderLineItem\Struct\OrderLineItemBasicStruct;
use Shopware\Framework\ORM\Entity;

class OrderDeliveryPositionBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $orderDeliveryId;

    /**
     * @var string
     */
    protected $orderLineItemId;

    /**
     * @var float
     */
    protected $unitPrice;

    /**
     * @var float
     */
    protected $totalPrice;

    /**
     * @var float
     */
    protected $quantity;

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

    /**
     * @var OrderLineItemBasicStruct
     */
    protected $orderLineItem;

    public function getOrderDeliveryId(): string
    {
        return $this->orderDeliveryId;
    }

    public function setOrderDeliveryId(string $orderDeliveryId): void
    {
        $this->orderDeliveryId = $orderDeliveryId;
    }

    public function getOrderLineItemId(): string
    {
        return $this->orderLineItemId;
    }

    public function setOrderLineItemId(string $orderLineItemId): void
    {
        $this->orderLineItemId = $orderLineItemId;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): void
    {
        $this->totalPrice = $totalPrice;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): void
    {
        $this->quantity = $quantity;
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

    public function getOrderLineItem(): \Shopware\Checkout\Order\Aggregate\OrderLineItem\Struct\OrderLineItemBasicStruct
    {
        return $this->orderLineItem;
    }

    public function setOrderLineItem(\Shopware\Checkout\Order\Aggregate\OrderLineItem\Struct\OrderLineItemBasicStruct $orderLineItem): void
    {
        $this->orderLineItem = $orderLineItem;
    }
}
