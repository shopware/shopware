<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog;

use DateTime;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationCollection;
use Shopware\Core\Content\Media\Aggregate\MediaAlbum\MediaAlbumCollection;
use Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\MediaAlbumTranslationCollection;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationCollection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\ORM\Entity;
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
     * @var MediaCollection|null
     */
    protected $media;

    /**
     * @var MediaAlbumCollection|null
     */
    protected $mediaAlbum;

    /**
     * @var MediaAlbumTranslationCollection|null
     */
    protected $mediaAlbumTranslations;

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

    public function getMedia(): ?MediaCollection
    {
        return $this->media;
    }

    public function setMedia(MediaCollection $media): void
    {
        $this->media = $media;
    }

    public function getMediaAlbum(): ?MediaAlbumCollection
    {
        return $this->mediaAlbum;
    }

    public function setMediaAlbum(MediaAlbumCollection $mediaAlbum): void
    {
        $this->mediaAlbum = $mediaAlbum;
    }

    public function getMediaAlbumTranslations(): ?MediaAlbumTranslationCollection
    {
        return $this->mediaAlbumTranslations;
    }

    public function setMediaAlbumTranslations(MediaAlbumTranslationCollection $mediaAlbumTranslations): void
    {
        $this->mediaAlbumTranslations = $mediaAlbumTranslations;
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

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }
}
