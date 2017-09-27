<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Struct;

use Shopware\Framework\Struct\Struct;

class ProductDetailPriceBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $customerGroupUuid;

    /**
     * @var int
     */
    protected $quantityStart;

    /**
     * @var int|null
     */
    protected $quantityEnd;

    /**
     * @var string
     */
    protected $productDetailUuid;

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

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getCustomerGroupUuid(): string
    {
        return $this->customerGroupUuid;
    }

    public function setCustomerGroupUuid(string $customerGroupUuid): void
    {
        $this->customerGroupUuid = $customerGroupUuid;
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

    public function getProductDetailUuid(): string
    {
        return $this->productDetailUuid;
    }

    public function setProductDetailUuid(string $productDetailUuid): void
    {
        $this->productDetailUuid = $productDetailUuid;
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
}
