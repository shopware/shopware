<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Framework\Struct\Struct;

class DeliveryInformation extends Struct
{
    /**
     * @var int
     */
    protected $stock;

    /**
     * @var float
     */
    protected $weight;

    /**
     * @var DeliveryDate
     */
    protected $inStockDeliveryDate;

    /**
     * @var DeliveryDate
     */
    protected $outOfStockDeliveryDate;

    public function __construct(int $stock, float $weight, DeliveryDate $inStockDeliveryDate, DeliveryDate $outOfStockDeliveryDate)
    {
        $this->stock = $stock;
        $this->weight = $weight;
        $this->inStockDeliveryDate = $inStockDeliveryDate;
        $this->outOfStockDeliveryDate = $outOfStockDeliveryDate;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): void
    {
        $this->stock = $stock;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function setWeight(float $weight): void
    {
        $this->weight = $weight;
    }

    public function getInStockDeliveryDate(): DeliveryDate
    {
        return $this->inStockDeliveryDate;
    }

    public function setInStockDeliveryDate(DeliveryDate $inStockDeliveryDate): void
    {
        $this->inStockDeliveryDate = $inStockDeliveryDate;
    }

    public function getOutOfStockDeliveryDate(): DeliveryDate
    {
        return $this->outOfStockDeliveryDate;
    }

    public function setOutOfStockDeliveryDate(DeliveryDate $outOfStockDeliveryDate): void
    {
        $this->outOfStockDeliveryDate = $outOfStockDeliveryDate;
    }
}
