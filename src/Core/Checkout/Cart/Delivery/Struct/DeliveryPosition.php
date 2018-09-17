<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Framework\Struct\Struct;

class DeliveryPosition extends Struct
{
    /**
     * @var LineItem
     */
    protected $lineItem;

    /**
     * @var float
     */
    protected $quantity;

    /**
     * @var Price
     */
    protected $price;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var DeliveryDate
     */
    protected $deliveryDate;

    public function __construct(
        string $identifier,
        LineItem $lineItem,
        int $quantity,
        Price $price,
        DeliveryDate $deliveryDate
    ) {
        $this->lineItem = $lineItem;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->identifier = $identifier;
        $this->deliveryDate = $deliveryDate;
    }

    public static function createByLineItemForInStockDate(LineItem $lineItem): self
    {
        return new self(
            $lineItem->getKey(),
            clone $lineItem,
            $lineItem->getQuantity(),
            $lineItem->getPrice(),
            $lineItem->getDeliveryInformation()->getInStockDeliveryDate()
        );
    }

    public static function createByLineItemForOutOfStockDate(LineItem $lineItem): self
    {
        return new self(
            $lineItem->getKey(),
            clone $lineItem,
            $lineItem->getQuantity(),
            $lineItem->getPrice(),
            $lineItem->getDeliveryInformation()->getOutOfStockDeliveryDate()
        );
    }

    public function getLineItem(): LineItem
    {
        return $this->lineItem;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getPrice(): Price
    {
        return $this->price;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getDeliveryDate(): DeliveryDate
    {
        return $this->deliveryDate;
    }
}
