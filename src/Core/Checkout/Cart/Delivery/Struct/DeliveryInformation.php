<?php declare(strict_types=1);

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
     * @var bool
     */
    protected $freeDelivery;

    /**
     * @var int|null
     */
    protected $restockTime;

    /**
     * @var DeliveryTime|null
     */
    protected $deliveryTime;

    public function __construct(
        int $stock,
        float $weight,
        bool $freeDelivery,
        ?int $restockTime = null,
        ?DeliveryTime $deliveryTime = null
    ) {
        $this->stock = $stock;
        $this->weight = $weight;
        $this->freeDelivery = $freeDelivery;
        $this->restockTime = $restockTime;
        $this->deliveryTime = $deliveryTime;
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

    public function getFreeDelivery(): bool
    {
        return $this->freeDelivery;
    }

    public function setFreeDelivery(bool $freeDelivery): void
    {
        $this->freeDelivery = $freeDelivery;
    }

    public function getRestockTime(): ?int
    {
        return $this->restockTime;
    }

    public function setRestockTime(?int $restockTime): void
    {
        $this->restockTime = $restockTime;
    }

    public function getDeliveryTime(): ?DeliveryTime
    {
        return $this->deliveryTime;
    }

    public function setDeliveryTime(?DeliveryTime $deliveryTime): void
    {
        $this->deliveryTime = $deliveryTime;
    }
}
