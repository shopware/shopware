<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog;

use DateTime;
use Shopware\Core\Content\Catalog\Aggregate\CatalogTranslation\CatalogTranslationCollection;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationCollection;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationCollection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

class CatalogStruct extends Entity
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var DateTime|null
     */
    protected $createdAt;

    /**
     * @var DateTime|null
     */
    protected $updatedAt;

    /**
     * @var CatalogCollection|null
     */
    protected $categories;

    /**
     * @var CategoryTranslationCollection|null
     */
    protected $categoryTranslations;

    /**
     * @var MediaTranslationCollection|null
     */
    protected $mediaTranslations;

    /**
     * @var ProductCollection|null
     */
    protected $products;

    /**
     * @var ProductManufacturerCollection|null
     */
    protected $productManufacturers;

    /**
     * @var ProductManufacturerTranslationCollection|null
     */
    protected $productManufacturerTranslations;

    /**
     * @var ProductMediaCollection|null
     */
    protected $productMedia;

    /**
     * @var ProductTranslationCollection|null
     */
    protected $productTranslations;

    /**
     * @var CatalogTranslationCollection|null
     */
    protected $translations;

    /**
     * @var SalesChannelCollection|null
     */
    protected $salesChannels;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getCategories(): ?CatalogCollection
    {
        return $this->categories;
    }

    public function setCategories(CatalogCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getCategoryTranslations(): ?CategoryTranslationCollection
    {
        return $this->categoryTranslations;
    }

    public function setCategoryTranslations(CategoryTranslationCollection $categoryTranslations): void
    {
        $this->categoryTranslations = $categoryTranslations;
    }

    public function getMediaTranslations(): ?MediaTranslationCollection
    {
        return $this->mediaTranslations;
    }

    public function setMediaTranslations(MediaTranslationCollection $mediaTranslations): void
    {
        $this->mediaTranslations = $mediaTranslations;
    }

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }

    public function getProductManufacturers(): ?ProductManufacturerCollection
    {
        return $this->productManufacturers;
    }

    public function setProductManufacturers(ProductManufacturerCollection $productManufacturers): void
    {
        $this->productManufacturers = $productManufacturers;
    }

    public function getProductManufacturerTranslations(): ?ProductManufacturerTranslationCollection
    {
        return $this->productManufacturerTranslations;
    }

    public function setProductManufacturerTranslations(ProductManufacturerTranslationCollection $productManufacturerTranslations): void
    {
        $this->productManufacturerTranslations = $productManufacturerTranslations;
    }

    public function getProductMedia(): ?ProductMediaCollection
    {
        return $this->productMedia;
    }

    public function setProductMedia(ProductMediaCollection $productMedia): void
    {
        $this->productMedia = $productMedia;
    }

    public function getProductTranslations(): ?ProductTranslationCollection
    {
        return $this->productTranslations;
    }

    public function setProductTranslations(ProductTranslationCollection $productTranslations): void
    {
        $this->productTranslations = $productTranslations;
    }

    public function getTranslations(): ?CatalogTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(CatalogTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }
}
