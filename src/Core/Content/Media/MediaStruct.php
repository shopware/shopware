<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Catalog\CatalogStruct;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\User\UserStruct;

class MediaStruct extends Entity
{
    /**
     * @var int
     */
    protected $catalogId;

    /**
     * @var string|null
     */
    protected $userId;

    /**
     * @var string|null
     */
    protected $mimeType;

    /**
     * @var string|null
     */
    protected $fileExtension;

    /**
     * @var int|null
     */
    protected $fileSize;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $metaData;

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
    protected $description;

    /**
     * @var string
     */
    protected $url = '';

    /**
     * @var UserStruct|null
     */
    protected $user;

    /**
     * @var MediaTranslationCollection|null
     */
    protected $translations;

    /**
     * @var CategoryCollection|null
     */
    protected $categories;

    /**
     * @var ProductManufacturerCollection|null
     */
    protected $productManufacturers;

    /**
     * @var ProductMediaCollection|null
     */
    protected $productMedia;

    /**
     * @var CatalogStruct|null
     */
    protected $catalog;

    /**
     * @var MediaThumbnailCollection|null
     */
    protected $thumbnails;

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getFileExtension(): ?string
    {
        return $this->fileExtension;
    }

    public function setFileExtension(string $fileExtension): void
    {
        $this->fileExtension = $fileExtension;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): void
    {
        $this->fileSize = $fileSize;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getMetaData(): ?string
    {
        return $this->metaData;
    }

    public function setMetaData(?string $metaData): void
    {
        $this->metaData = $metaData;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getUser(): ?UserStruct
    {
        return $this->user;
    }

    public function setUser(UserStruct $user): void
    {
        $this->user = $user;
    }

    public function getTranslations(): ?MediaTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(MediaTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getCategories(): ?CategoryCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getProductManufacturers(): ?ProductManufacturerCollection
    {
        return $this->productManufacturers;
    }

    public function setProductManufacturers(ProductManufacturerCollection $productManufacturers): void
    {
        $this->productManufacturers = $productManufacturers;
    }

    public function getProductMedia(): ?ProductMediaCollection
    {
        return $this->productMedia;
    }

    public function setProductMedia(ProductMediaCollection $productMedia): void
    {
        $this->productMedia = $productMedia;
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

    public function getThumbnails(): MediaThumbnailCollection
    {
        if ($this->thumbnails === null) {
            $this->thumbnails = new MediaThumbnailCollection();
        }

        return $this->thumbnails;
    }

    public function setThumbnails(MediaThumbnailCollection $thumbnailCollection): void
    {
        $this->thumbnails = $thumbnailCollection;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url)
    {
        $this->url = $url;
    }
}
