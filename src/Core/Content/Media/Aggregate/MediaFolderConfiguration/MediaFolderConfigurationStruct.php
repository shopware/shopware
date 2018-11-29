<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class MediaFolderConfigurationStruct extends Entity
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var MediaFolderCollection
     */
    protected $mediaFolders;

    /**
     * @var bool
     */
    protected $createThumbnails;

    /**
     * @var MediaThumbnailSizeCollection
     */
    protected $mediaThumbnailSizes;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getMediaFolders(): ?MediaFolderCollection
    {
        return $this->mediaFolders;
    }

    public function setMediaFolders(?MediaFolderCollection $mediaFolders): void
    {
        $this->mediaFolders = $mediaFolders;
    }

    public function getCreateThumbnails(): bool
    {
        return $this->createThumbnails;
    }

    public function setCreateThumbnails(bool $createThumbnails): void
    {
        $this->createThumbnails = $createThumbnails;
    }

    public function getMediaThumbnailSizes(): MediaThumbnailSizeCollection
    {
        return $this->mediaThumbnailSizes;
    }

    public function setMediaThumbnailSizes(MediaThumbnailSizeCollection $mediaThumbnailSizes): void
    {
        $this->mediaThumbnailSizes = $mediaThumbnailSizes;
    }
}
