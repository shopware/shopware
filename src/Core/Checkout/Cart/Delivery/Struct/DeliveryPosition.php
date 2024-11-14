<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class DeliveryPosition extends Struct
{
    /**
     * @var LineItem
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $lineItem;

    /**
     * @var float
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $quantity;

    /**
     * @var CalculatedPrice
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $price;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $identifier;

    /**
     * @var DeliveryDate
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $deliveryDate;

    public function __construct(
        string $identifier,
        LineItem $lineItem,
        int $quantity,
        CalculatedPrice $price,
        DeliveryDate $deliveryDate
    ) {
        $this->lineItem = $lineItem;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->identifier = $identifier;
        $this->deliveryDate = $deliveryDate;
    }

    public function getLineItem(): LineItem
    {
        return $this->lineItem;
    }

    public function getQuantity(): float
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
