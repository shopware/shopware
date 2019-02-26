<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use DateTime;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationCollection;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Metadata\Metadata;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserEntity;

class MediaEntity extends Entity
{
    use EntityIdTrait;
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
     * @var string|null
     */
    protected $metaDataRaw;

    /**
     * @var string|null
     */
    protected $mediaTypeRaw;

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
    protected $alt;

    /**
     * @var string
     */
    protected $url = '';

    /**
     * @var string|null
     */
    protected $fileName;

    /**
     * @var UserEntity|null
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
     * @var MediaFolderEntity | null
     */
    protected $mediaFolder;

    /**
     * @var bool
     */
    protected $hasFile = false;

    /**
     * @var ConfigurationGroupOptionCollection|null
     */
    protected $configurationGroupOptions;

    /**
     * @var array|null
     */
    protected $attributes;

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

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): void
    {
        $this->alt = $alt;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(UserEntity $user): void
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

    public function setFileName(string $fileName): void
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

    public function getMediaFolder(): ?MediaFolderEntity
    {
        return $this->mediaFolder;
    }

    public function setMediaFolder(?MediaFolderEntity $mediaFolder): void
    {
        $this->mediaFolder = $mediaFolder;
    }

    public function getConfigurationGroupOptions(): ?ConfigurationGroupOptionCollection
    {
        return $this->configurationGroupOptions;
    }

    public function setConfigurationGroupOptions(ConfigurationGroupOptionCollection $configurationGroupOptions): void
    {
        $this->configurationGroupOptions = $configurationGroupOptions;
    }

    public function getMetaDataRaw(): ?string
    {
        return $this->metaDataRaw;
    }

    public function setMetaDataRaw(string $metaDataRaw): void
    {
        $this->metaDataRaw = $metaDataRaw;
    }

    public function getMediaTypeRaw(): ?string
    {
        return $this->mediaTypeRaw;
    }

    public function setMediaTypeRaw(string $mediaTypeRaw): void
    {
        $this->mediaTypeRaw = $mediaTypeRaw;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        unset($data['metaDataRaw'], $data['mediaTypeRaw']);

        return $data;
    }
}
