<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize;

use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class MediaThumbnailSizeEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var int<1, max>
     */
    protected int $width;

    /**
     * @var int<1, max>
     */
    protected int $height;

    protected ?MediaFolderConfigurationCollection $mediaFolderConfigurations = null;

    /**
     * @return int<1, max>
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * @param int<1, max> $width
     */
    public function setWidth(int $width): void
    {
        $this->width = $width;
    }

    /**
     * @return int<1, max>
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @param int<1, max> $height
     */
    public function setHeight(int $height): void
    {
        $this->height = $height;
    }

    public function getMediaFolderConfigurations(): ?MediaFolderConfigurationCollection
    {
        return $this->mediaFolderConfigurations;
    }

    public function setMediaFolderConfigurations(MediaFolderConfigurationCollection $mediaFolderConfigurations): void
    {
        $this->mediaFolderConfigurations = $mediaFolderConfigurations;
    }
}
