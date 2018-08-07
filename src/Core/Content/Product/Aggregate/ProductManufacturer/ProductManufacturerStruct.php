<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturer;

use Shopware\Core\Content\Catalog\CatalogStruct;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\ORM\Entity;

class ProductManufacturerStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $mediaId;

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
    protected $link;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

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
     * @var MediaStruct|null
     */
    protected $media;

    /**
     * @var ProductManufacturerTranslationCollection|null
     */
    protected $translations;

    /**
     * @var ProductCollection|null
     */
    protected $products;

    /**
     * @var CatalogStruct|null
     */
    protected $catalog;

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): void
    {
        $this->link = $link;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
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

    public function getMedia(): ?MediaStruct
    {
        return $this->media;
    }

    public function setMedia(MediaStruct $media): void
    {
        $this->media = $media;
    }

    public function getTranslations(): ?ProductManufacturerTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ProductManufacturerTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
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

    public function setCatalog(CatalogStruct $catalog): void
    {
        $this->catalog = $catalog;
    }
}
