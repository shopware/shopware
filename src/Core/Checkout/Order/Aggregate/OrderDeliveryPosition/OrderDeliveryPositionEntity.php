<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OrderDeliveryPositionEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $orderDeliveryId;

    /**
     * @var string
     */
    protected $orderLineItemId;

    /**
     * @var CalculatedPrice|null
     */
    protected $price;

    /**
     * @var float
     */
    protected $unitPrice;

    /**
     * @var float
     */
    protected $totalPrice;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var OrderLineItemEntity|null
     */
    protected $orderLineItem;

    /**
     * @var OrderDeliveryEntity|null
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
}
