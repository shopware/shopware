<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderStruct;
use Shopware\Core\Content\Media\Aggregate\ThumbnailSize\ThumbnailSizeCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class MediaFolderConfigurationStruct extends Entity
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $mediaFolderId;

    /**
     * @var MediaFolderStruct
     */
    protected $mediaFolder;

    /**
     * @var bool
     */
    protected $autoCreateThumbnails;

    /**
     * @var ThumbnailSizeCollection
     */
    protected $thumbnailSizes;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getMediaFolderId(): string
    {
        return $this->mediaFolderId;
    }

    public function setMediaFolderId(string $mediaFolderId): void
    {
        $this->mediaFolderId = $mediaFolderId;
    }

    public function getMediaFolder(): MediaFolderStruct
    {
        return $this->mediaFolder;
    }

    public function setMediaFolder(MediaFolderStruct $mediaFolder): void
    {
        $this->mediaFolder = $mediaFolder;
    }

    public function getAutoCreateThumbnails(): bool
    {
        return $this->autoCreateThumbnails;
    }

    public function setAutoCreateThumbnails(bool $createThumbnails): void
    {
        $this->autoCreateThumbnails = $createThumbnails;
    }

    public function getThumbnailSizes(): ThumbnailSizeCollection
    {
        return $this->thumbnailSizes;
    }

    public function setThumbnailSizes(ThumbnailSizeCollection $thumbnailSizes): void
    {
        $this->thumbnailSizes = $thumbnailSizes;
    }
}
