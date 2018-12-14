<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolder;

use Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderStruct;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationStruct;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class MediaFolderStruct extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

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
    protected $configurationId;

    /**
     * @var MediaFolderConfigurationStruct|null
     */
    protected $configuration;

    /**
     * @var MediaDefaultFolderStruct|null
     */
    protected $defaultFolder;

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

    public function getConfigurationId(): ?string
    {
        return $this->configurationId;
    }

    public function setConfigurationId(?string $configurationId): void
    {
        $this->configurationId = $configurationId;
    }

    public function getConfiguration(): ?MediaFolderConfigurationStruct
    {
        return $this->configuration;
    }

    public function setConfiguration(?MediaFolderConfigurationStruct $configuration): void
    {
        $this->configuration = $configuration;
    }

    /**
     * @return MediaDefaultFolderStruct|null
     */
    public function getDefaultFolder(): ?MediaDefaultFolderStruct
    {
        return $this->defaultFolder;
    }

    /**
     * @param MediaDefaultFolderStruct|null $defaultFolder
     */
    public function setDefaultFolder(?MediaDefaultFolderStruct $defaultFolder): void
    {
        $this->defaultFolder = $defaultFolder;
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
