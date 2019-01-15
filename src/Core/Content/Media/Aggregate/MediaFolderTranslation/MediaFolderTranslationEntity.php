<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolderTranslation;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class MediaFolderTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $mediaFolderId;

    /**
     * @var MediaFolderEntity
     */
    protected $mediaFolder;

    /**
     * @var string
     */
    protected $name;

    public function getMediaFolderId(): string
    {
        return $this->mediaFolderId;
    }

    public function setMediaFolderId(string $mediaFolderId): void
    {
        $this->mediaFolderId = $mediaFolderId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getMediaFolder(): MediaFolderEntity
    {
        return $this->mediaFolder;
    }

    public function setMediaFolder(MediaFolderEntity $mediaFolder): void
    {
        $this->mediaFolder = $mediaFolder;
    }
}
