<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class OrderDeliveryPositionEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $orderDeliveryId;

    protected string $orderLineItemId;

    protected ?CalculatedPrice $price = null;

    protected float $unitPrice;

    protected float $totalPrice;

    protected int $quantity;

    protected ?OrderLineItemEntity $orderLineItem = null;

    protected ?OrderDeliveryEntity $orderDelivery = null;

    protected string $orderDeliveryVersionId;

    protected string $orderLineItemVersionId;

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

    public function getPrice(): ?CalculatedPrice
    {
        return $this->price;
    }

    public function setPrice(?CalculatedPrice $price): void
    {
        $this->price = $price;
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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getOrderLineItem(): ?OrderLineItemEntity
    {
        return $this->orderLineItem;
    }

    public function setOrderLineItem(OrderLineItemEntity $orderLineItem): void
    {
        $this->orderLineItem = $orderLineItem;
    }

    public function getOrderDelivery(): ?OrderDeliveryEntity
    {
        return $this->orderDelivery;
    }

    public function setOrderDelivery(OrderDeliveryEntity $orderDelivery): void
    {
        $this->orderDelivery = $orderDelivery;
    }

    public function getOrderDeliveryVersionId(): string
    {
        return $this->orderDeliveryVersionId;
    }

    public function setOrderDeliveryVersionId(string $orderDeliveryVersionId): void
    {
        $this->orderDeliveryVersionId = $orderDeliveryVersionId;
    }

    public function getOrderLineItemVersionId(): string
    {
        return $this->orderLineItemVersionId;
    }

    public function setOrderLineItemVersionId(string $orderLineItemVersionId): void
    {
        $this->orderLineItemVersionId = $orderLineItemVersionId;
    }
}
