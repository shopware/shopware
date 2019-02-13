<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize;

use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MediaThumbnailSizeEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var int
     */
    protected $width;

    /**
     * @var int
     */
    protected $height;

    /**
     * @var MediaFolderConfigurationCollection|null
     */
    protected $mediaFolderConfigurations;

    /**
     * @var array|null
     */
    protected $attributes;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

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

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
