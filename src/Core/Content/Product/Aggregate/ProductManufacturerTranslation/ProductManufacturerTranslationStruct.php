<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation;

use Shopware\Core\Content\Catalog\CatalogStruct;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerStruct;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Language\LanguageStruct;

class ProductManufacturerTranslationStruct extends Entity
{
    /**
     * @var string
     */
    protected $productManufacturerId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var int
     */
    protected $catalogId;

    /**
     * @var string
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
     * @var ProductManufacturerStruct|null
     */
    protected $productManufacturer;

    /**
     * @var LanguageStruct|null
     */
    protected $language;

    /**
     * @var CatalogStruct|null
     */
    protected $catalog;

    public function getLanguage(): ?LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(?LanguageStruct $language): void
    {
        $this->language = $language;
    }

    public function getProductManufacturerId(): string
    {
        return $this->productManufacturerId;
    }

    public function setProductManufacturerId(string $productManufacturerId): void
    {
        $this->productManufacturerId = $productManufacturerId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
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

    public function getProductManufacturer(): ?ProductManufacturerStruct
    {
        return $this->productManufacturer;
    }

    public function setProductManufacturer(ProductManufacturerStruct $productManufacturer): void
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

    public function getCatalog(): ?CatalogStruct
    {
        return $this->catalog;
    }

    public function setCatalog(?CatalogStruct $catalog): void
    {
        $this->catalog = $catalog;
    }
}
