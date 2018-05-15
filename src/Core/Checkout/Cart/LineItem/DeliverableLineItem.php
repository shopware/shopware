<?php declare(strict_types=1);

namespace Shopware\Checkout\Cart\LineItem;

use Shopware\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Application\Context\Rule\Rule;

class DeliverableLineItem extends CalculatedLineItem implements DeliverableLineItemInterface
{
    /**
     * @var null|Delivery
     */
    protected $delivery;

    /**
     * @var null|Rule
     */
    protected $rule;

    /**
     * @var int
     */
    protected $stock;

    /**
     * @var float
     */
    protected $weight;

    /**
     * @var \Shopware\Checkout\Cart\Delivery\Struct\DeliveryDate
     */
    protected $inStockDeliveryDate;

    /**
     * @var \Shopware\Checkout\Cart\Delivery\Struct\DeliveryDate
     */
    protected $outOfStockDeliveryDate;

    public function __construct(
        string $identifier,
        CalculatedPrice $price,
        int $quantity,
        string $label,
        string $type,
        int $stock,
        int $weight,
        DeliveryDate $inStockDeliveryDate,
        DeliveryDate $outOfStockDeliveryDate,
        ?LineItemInterface $lineItem = null,
        ?Rule $rule = null
    ) {
        parent::__construct($identifier, $price, $quantity, $type, $label, $lineItem, $rule);
        $this->stock = $stock;
        $this->weight = $weight;
        $this->inStockDeliveryDate = $inStockDeliveryDate;
        $this->outOfStockDeliveryDate = $outOfStockDeliveryDate;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function getInStockDeliveryDate(): DeliveryDate
    {
        return $this->inStockDeliveryDate;
    }

    public function getOutOfStockDeliveryDate(): DeliveryDate
    {
        return $this->outOfStockDeliveryDate;
    }

    public function getDelivery(): ? Delivery
    {
        return $this->delivery;
    }

    public function setDelivery(?Delivery $delivery): void
    {
        $this->delivery = $delivery;
    }
}
