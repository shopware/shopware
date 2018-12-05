<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolder;

use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationStruct;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class MediaFolderStruct extends Entity
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var MediaFolderStruct| null
     */
    protected $parent;

    /**
     * @var int
     */
    protected $childCount;

    /**
     * @var MediaCollection
     */
    protected $media;

    /**
     * @var string| null
     */
    protected $mediaFolderConfigurationId;

    /**
     * @var MediaFolderConfigurationStruct|null
     */
    protected $configuration;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var bool
     */
    protected $useParentConfiguration;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getParent(): ?MediaFolderStruct
    {
        return $this->parent;
    }

    public function setParent(?MediaFolderStruct $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildCount(): int
    {
        return $this->childCount;
    }

    public function setChildCount(int $childCount): void
    {
        $this->childCount = $childCount;
    }

    public function getMedia(): MediaCollection
    {
        return $this->media;
    }

    public function setMedia(MediaCollection $media): void
    {
        $this->media = $media;
    }

    public function getMediaFolderConfigurationId(): ?string
    {
        return $this->mediaFolderConfigurationId;
    }

    public function setMediaFolderConfigurationId(?string $mediaFolderConfigurationId): void
    {
        $this->mediaFolderConfigurationId = $mediaFolderConfigurationId;
    }

    public function getConfiguration(): ?MediaFolderConfigurationStruct
    {
        return $this->configuration;
    }

    public function setConfiguration(?MediaFolderConfigurationStruct $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ? \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function isUseParentConfiguration(): bool
    {
        return $this->useParentConfiguration;
    }

    public function setUseParentConfiguration(bool $useParentConfiguration): void
    {
        $this->useParentConfiguration = $useParentConfiguration;
    }
}
