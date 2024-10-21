<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize;

use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class MediaThumbnailSizeEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @deprecated tag:v6.7.0 - Will be natively typed
     *
     * @var int<1, max>
     */
    protected $width;

    /**
     * @deprecated tag:v6.7.0 - Will be natively typed
     *
     * @var int<1, max>
     */
    protected $height;

    /**
     * @deprecated tag:v6.7.0 - Will be natively typed
     *
     * @var MediaFolderConfigurationCollection|null
     */
    protected $mediaFolderConfigurations;

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
