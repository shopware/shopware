<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Customer\Struct\CustomerGroupBasicStruct;
use Shopware\Api\Entity\Entity;

class ProductPriceBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $customerGroupId;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var int
     */
    protected $quantityStart;

    /**
     * @var int|null
     */
    protected $quantityEnd;

    /**
     * @var float
     */
    protected $price;

    /**
     * @var float|null
     */
    protected $pseudoPrice;

    /**
     * @var float|null
     */
    protected $basePrice;

    /**
     * @var float|null
     */
    protected $percentage;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var CustomerGroupBasicStruct
     */
    protected $customerGroup;

    public function getCustomerGroupId(): string
    {
        return $this->customerGroupId;
    }

    public function setCustomerGroupId(string $customerGroupId): void
    {
        $this->customerGroupId = $customerGroupId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getQuantityStart(): int
    {
        return $this->quantityStart;
    }

    public function setQuantityStart(int $quantityStart): void
    {
        $this->quantityStart = $quantityStart;
    }

    public function getQuantityEnd(): ?int
    {
        return $this->quantityEnd;
    }

    public function setQuantityEnd(?int $quantityEnd): void
    {
        $this->quantityEnd = $quantityEnd;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getPseudoPrice(): ?float
    {
        return $this->pseudoPrice;
    }

    public function setPseudoPrice(?float $pseudoPrice): void
    {
        $this->pseudoPrice = $pseudoPrice;
    }

    public function getBasePrice(): ?float
    {
        return $this->basePrice;
    }

    public function setBasePrice(?float $basePrice): void
    {
        $this->basePrice = $basePrice;
    }

    public function getPercentage(): ?float
    {
        return $this->percentage;
    }

    public function setPercentage(?float $percentage): void
    {
        $this->percentage = $percentage;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getCustomerGroup(): CustomerGroupBasicStruct
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroupBasicStruct $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }
}
