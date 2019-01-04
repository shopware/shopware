<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation;

use Shopware\Core\Content\Catalog\CatalogEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class ProductManufacturerTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $productManufacturerId;

    /**
     * @var int
     */
    protected $catalogId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $metaTitle;

    /**
     * @var string|null
     */
    protected $metaDescription;

    /**
     * @var string|null
     */
    protected $metaKeywords;

    /**
     * @var ProductManufacturerEntity|null
     */
    protected $productManufacturer;

    /**
     * @var CatalogEntity|null
     */
    protected $catalog;

    public function getProductManufacturerId(): string
    {
        return $this->productManufacturerId;
    }

    public function setProductManufacturerId(string $productManufacturerId): void
    {
        $this->productManufacturerId = $productManufacturerId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function getMetaKeywords(): ?string
    {
        return $this->metaKeywords;
    }

    public function setMetaKeywords(?string $metaKeywords): void
    {
        $this->metaKeywords = $metaKeywords;
    }

    public function getProductManufacturer(): ?ProductManufacturerEntity
    {
        return $this->productManufacturer;
    }

    public function setProductManufacturer(ProductManufacturerEntity $productManufacturer): void
    {
        $this->productManufacturer = $productManufacturer;
    }

    public function getCatalogId(): int
    {
        return $this->catalogId;
    }

    public function setCatalogId(int $catalogId): void
    {
        $this->catalogId = $catalogId;
    }

    public function getCatalog(): ?CatalogEntity
    {
        return $this->catalog;
    }

    public function setCatalog(CatalogEntity $catalog): void
    {
        $this->catalog = $catalog;
    }
}
