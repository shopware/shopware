<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class DeliveryPosition extends Struct
{
    public function __construct(
        protected string $identifier,
        protected LineItem $lineItem,
        protected int $quantity,
        protected CalculatedPrice $price,
        protected DeliveryDate $deliveryDate
    ) {
    }

    public function getLineItem(): LineItem
    {
        return $this->lineItem;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getPrice(): CalculatedPrice
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

    public function getApiAlias(): string
    {
        return 'cart_delivery_position';
    }
}
