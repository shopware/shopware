<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class OrderDeliveryPositionStruct extends Entity
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
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var OrderLineItemStruct
     */
    protected $orderLineItem;

    /**
     * @var OrderDeliveryStruct|null
     */
    protected $orderDelivery;

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

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getOrderLineItem(): OrderLineItemStruct
    {
        return $this->orderLineItem;
    }

    public function setOrderLineItem(OrderLineItemStruct $orderLineItem): void
    {
        $this->orderLineItem = $orderLineItem;
    }

    public function getOrderDelivery(): ?OrderDeliveryStruct
    {
        return $this->orderDelivery;
    }

    public function setOrderDelivery(OrderDeliveryStruct $orderDelivery): void
    {
        $this->orderDelivery = $orderDelivery;
    }
}
