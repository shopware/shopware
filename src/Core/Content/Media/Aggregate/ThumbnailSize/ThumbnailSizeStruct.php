<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\ThumbnailSize;

use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class ThumbnailSizeStruct extends Entity
{
    /**
     * @var int
     */
    protected $width;

    /**
     * @var int
     */
    protected $height;

    /**
     * @var MediaFolderConfigurationCollection
     */
    protected $mediaFolderConfigurations;

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): void
    {
        $this->height = $height;
    }

    public function getMediaFolderConfigurations(): MediaFolderConfigurationCollection
    {
        return $this->mediaFolderConfigurations;
    }

    public function setMediaFolderConfigurations(MediaFolderConfigurationCollection $mediaFolderConfigurations): void
    {
        $this->mediaFolderConfigurations = $mediaFolderConfigurations;
    }
}
