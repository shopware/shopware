<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use DateTime;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderStruct;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationCollection;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Metadata\Metadata;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\User\UserStruct;

class MediaStruct extends Entity
{
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
     * @var string|null
     */
    protected $title;

    /**
     * @var Metadata|null
     */
    protected $metaData;

    /**
     * @var MediaType|null
     */
    protected $mediaType;

    /**
     * @var DateTime|null
     */
    protected $createdAt;

    /**
     * @var DateTime|null
     */
    protected $updatedAt;

    /**
     * @var DateTime|null
     */
    protected $uploadedAt;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string
     */
    protected $url = '';

    /**
     * @var string|null
     */
    protected $fileName;

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
     * @var MediaThumbnailCollection
     */
    protected $thumbnails;

    /**
     * @var string | null
     */
    protected $mediaFolderId;

    /**
     * @var MediaFolderStruct | null
     */
    protected $mediaFolder;

    /**
     * @var bool
     */
    protected $hasFile = false;

    public function get(string $property)
    {
        if ($property === 'hasFile') {
            return $this->hasFile();
        }

        return parent::get($property);
    }

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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getMetaData(): ?Metadata
    {
        return $this->metaData;
    }

    public function setMetaData(Metadata $metaData): void
    {
        $this->metaData = $metaData;
    }

    public function getMediaType(): ?MediaType
    {
        return $this->mediaType;
    }

    public function setMediaType(?MediaType $mediaType): void
    {
        $this->mediaType = $mediaType;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getUploadedAt(): ?DateTime
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(?DateTime $uploadedAt): void
    {
        $this->uploadedAt = $uploadedAt;
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

    public function getThumbnails(): MediaThumbnailCollection
    {
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

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function hasFile(): bool
    {
        return $this->hasFile = ($this->mimeType !== null && $this->fileExtension !== null && $this->fileName !== null);
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName)
    {
        $this->fileName = $fileName;
    }

    public function getMediaFolderId(): ? string
    {
        return $this->mediaFolderId;
    }

    public function setMediaFolderId(?string $mediaFolderId): void
    {
        $this->mediaFolderId = $mediaFolderId;
    }

    public function getMediaFolder(): ?MediaFolderStruct
    {
        return $this->mediaFolder;
    }

    public function setMediaFolder(?MediaFolderStruct $mediaFolder): void
    {
        $this->mediaFolder = $mediaFolder;
    }
}
