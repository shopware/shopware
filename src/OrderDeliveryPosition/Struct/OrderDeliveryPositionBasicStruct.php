<?php declare(strict_types=1);

namespace Shopware\OrderDeliveryPosition\Struct;

use Shopware\Framework\Struct\Struct;
use Shopware\OrderLineItem\Struct\OrderLineItemBasicStruct;

class OrderDeliveryPositionBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $orderDeliveryUuid;

    /**
     * @var string
     */
    protected $orderLineItemUuid;

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
     * @var OrderLineItemBasicStruct
     */
    protected $lineItem;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getOrderDeliveryUuid(): string
    {
        return $this->orderDeliveryUuid;
    }

    public function setOrderDeliveryUuid(string $orderDeliveryUuid): void
    {
        $this->orderDeliveryUuid = $orderDeliveryUuid;
    }

    public function getOrderLineItemUuid(): string
    {
        return $this->orderLineItemUuid;
    }

    public function setOrderLineItemUuid(string $orderLineItemUuid): void
    {
        $this->orderLineItemUuid = $orderLineItemUuid;
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

    public function getLineItem(): OrderLineItemBasicStruct
    {
        return $this->lineItem;
    }

    public function setLineItem(OrderLineItemBasicStruct $lineItem): void
    {
        $this->lineItem = $lineItem;
    }
}
