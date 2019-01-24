<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice;

use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ShippingMethodPriceEntity extends Entity
{
    use EntityIdTrait;
    /**
     * @var string
     */
    protected $shippingMethodId;

    /**
     * @var float
     */
    protected $quantityFrom;

    /**
     * @var float
     */
    protected $price;

    /**
     * @var float
     */
    protected $factor;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var ShippingMethodEntity|null
     */
    protected $shippingMethod;

    public function getShippingMethodId(): string
    {
        return $this->shippingMethodId;
    }

    public function setShippingMethodId(string $shippingMethodId): void
    {
        $this->shippingMethodId = $shippingMethodId;
    }

    public function getQuantityFrom(): float
    {
        return $this->quantityFrom;
    }

    public function setQuantityFrom(float $quantityFrom): void
    {
        $this->quantityFrom = $quantityFrom;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getFactor(): float
    {
        return $this->factor;
    }

    public function setFactor(float $factor): void
    {
        $this->factor = $factor;
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

    public function getShippingMethod(): ?ShippingMethodEntity
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodEntity $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }
}
