<?php declare(strict_types=1);

namespace Shopware\ProductDetail\Struct;

use Shopware\Framework\Struct\Struct;
use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicCollection;
use Shopware\Unit\Struct\UnitBasicStruct;

class ProductDetailBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $productUuid;

    /**
     * @var string|null
     */
    protected $supplierNumber;

    /**
     * @var bool
     */
    protected $isMain;

    /**
     * @var int
     */
    protected $sales;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var int
     */
    protected $stock;

    /**
     * @var int|null
     */
    protected $minStock;

    /**
     * @var float|null
     */
    protected $weight;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var float|null
     */
    protected $width;

    /**
     * @var float|null
     */
    protected $height;

    /**
     * @var float|null
     */
    protected $length;

    /**
     * @var string|null
     */
    protected $ean;

    /**
     * @var string|null
     */
    protected $unitUuid;

    /**
     * @var int|null
     */
    protected $purchaseSteps;

    /**
     * @var int|null
     */
    protected $maxPurchase;

    /**
     * @var int
     */
    protected $minPurchase;

    /**
     * @var float|null
     */
    protected $purchaseUnit;

    /**
     * @var float|null
     */
    protected $referenceUnit;

    /**
     * @var \DateTime|null
     */
    protected $releaseDate;

    /**
     * @var bool
     */
    protected $shippingFree;

    /**
     * @var float
     */
    protected $purchasePrice;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var string|null
     */
    protected $additionalText;

    /**
     * @var string|null
     */
    protected $packUnit;

    /**
     * @var UnitBasicStruct|null
     */
    protected $unit;

    /**
     * @var ProductDetailPriceBasicCollection
     */
    protected $prices;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getProductUuid(): string
    {
        return $this->productUuid;
    }

    public function setProductUuid(string $productUuid): void
    {
        $this->productUuid = $productUuid;
    }

    public function getSupplierNumber(): ?string
    {
        return $this->supplierNumber;
    }

    public function setSupplierNumber(?string $supplierNumber): void
    {
        $this->supplierNumber = $supplierNumber;
    }

    public function getIsMain(): bool
    {
        return $this->isMain;
    }

    public function setIsMain(bool $isMain): void
    {
        $this->isMain = $isMain;
    }

    public function getSales(): int
    {
        return $this->sales;
    }

    public function setSales(int $sales): void
    {
        $this->sales = $sales;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): void
    {
        $this->stock = $stock;
    }

    public function getMinStock(): ?int
    {
        return $this->minStock;
    }

    public function setMinStock(?int $minStock): void
    {
        $this->minStock = $minStock;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): void
    {
        $this->weight = $weight;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getWidth(): ?float
    {
        return $this->width;
    }

    public function setWidth(?float $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): ?float
    {
        return $this->height;
    }

    public function setHeight(?float $height): void
    {
        $this->height = $height;
    }

    public function getLength(): ?float
    {
        return $this->length;
    }

    public function setLength(?float $length): void
    {
        $this->length = $length;
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function setEan(?string $ean): void
    {
        $this->ean = $ean;
    }

    public function getUnitUuid(): ?string
    {
        return $this->unitUuid;
    }

    public function setUnitUuid(?string $unitUuid): void
    {
        $this->unitUuid = $unitUuid;
    }

    public function getPurchaseSteps(): ?int
    {
        return $this->purchaseSteps;
    }

    public function setPurchaseSteps(?int $purchaseSteps): void
    {
        $this->purchaseSteps = $purchaseSteps;
    }

    public function getMaxPurchase(): ?int
    {
        return $this->maxPurchase;
    }

    public function setMaxPurchase(?int $maxPurchase): void
    {
        $this->maxPurchase = $maxPurchase;
    }

    public function getMinPurchase(): int
    {
        return $this->minPurchase;
    }

    public function setMinPurchase(int $minPurchase): void
    {
        $this->minPurchase = $minPurchase;
    }

    public function getPurchaseUnit(): ?float
    {
        return $this->purchaseUnit;
    }

    public function setPurchaseUnit(?float $purchaseUnit): void
    {
        $this->purchaseUnit = $purchaseUnit;
    }

    public function getReferenceUnit(): ?float
    {
        return $this->referenceUnit;
    }

    public function setReferenceUnit(?float $referenceUnit): void
    {
        $this->referenceUnit = $referenceUnit;
    }

    public function getReleaseDate(): ?\DateTime
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?\DateTime $releaseDate): void
    {
        $this->releaseDate = $releaseDate;
    }

    public function getShippingFree(): bool
    {
        return $this->shippingFree;
    }

    public function setShippingFree(bool $shippingFree): void
    {
        $this->shippingFree = $shippingFree;
    }

    public function getPurchasePrice(): float
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(float $purchasePrice): void
    {
        $this->purchasePrice = $purchasePrice;
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

    public function getAdditionalText(): ?string
    {
        return $this->additionalText;
    }

    public function setAdditionalText(?string $additionalText): void
    {
        $this->additionalText = $additionalText;
    }

    public function getPackUnit(): ?string
    {
        return $this->packUnit;
    }

    public function setPackUnit(?string $packUnit): void
    {
        $this->packUnit = $packUnit;
    }

    public function getUnit(): ?UnitBasicStruct
    {
        return $this->unit;
    }

    public function setUnit(?UnitBasicStruct $unit): void
    {
        $this->unit = $unit;
    }

    public function getPrices(): ProductDetailPriceBasicCollection
    {
        return $this->prices;
    }

    public function setPrices(ProductDetailPriceBasicCollection $prices): void
    {
        $this->prices = $prices;
    }
}
