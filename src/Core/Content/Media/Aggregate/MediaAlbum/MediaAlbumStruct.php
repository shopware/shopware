<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbum;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;

class MediaAlbumStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var bool
     */
    protected $createThumbnails;

    /**
     * @var string|null
     */
    protected $thumbnailSize;

    /**
     * @var string|null
     */
    protected $icon;

    /**
     * @var bool
     */
    protected $thumbnailHighDpi;

    /**
     * @var int|null
     */
    protected $thumbnailQuality;

    /**
     * @var int|null
     */
    protected $thumbnailHighDpiQuality;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var MediaAlbumStruct|null
     */
    protected $parent;

    /**
     * @var EntitySearchResult|null
     */
    protected $media;

    /**
     * @var EntitySearchResult|null
     */
    protected $children;

    /**
     * @var EntitySearchResult|null
     */
    protected $translations;

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getCreateThumbnails(): bool
    {
        return $this->createThumbnails;
    }

    public function setCreateThumbnails(bool $createThumbnails): void
    {
        $this->createThumbnails = $createThumbnails;
    }

    public function getThumbnailSize(): ?string
    {
        return $this->thumbnailSize;
    }

    public function setThumbnailSize(?string $thumbnailSize): void
    {
        $this->thumbnailSize = $thumbnailSize;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
    }

    public function getThumbnailHighDpi(): bool
    {
        return $this->thumbnailHighDpi;
    }

    public function setThumbnailHighDpi(bool $thumbnailHighDpi): void
    {
        $this->thumbnailHighDpi = $thumbnailHighDpi;
    }

    public function getThumbnailQuality(): ?int
    {
        return $this->thumbnailQuality;
    }

    public function setThumbnailQuality(?int $thumbnailQuality): void
    {
        $this->thumbnailQuality = $thumbnailQuality;
    }

    public function getThumbnailHighDpiQuality(): ?int
    {
        return $this->thumbnailHighDpiQuality;
    }

    public function setThumbnailHighDpiQuality(?int $thumbnailHighDpiQuality): void
    {
        $this->thumbnailHighDpiQuality = $thumbnailHighDpiQuality;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getParent(): ?MediaAlbumStruct
    {
        return $this->parent;
    }

    public function setParent(MediaAlbumStruct $parent): void
    {
        $this->parent = $parent;
    }

    public function getMedia(): ?EntitySearchResult
    {
        return $this->media;
    }

    public function setMedia(EntitySearchResult $media): void
    {
        $this->media = $media;
    }

    public function getChildren(): ?EntitySearchResult
    {
        return $this->children;
    }

    public function setChildren(?EntitySearchResult $children): void
    {
        $this->children = $children;
    }

    public function getTranslations(): ?EntitySearchResult
    {
        return $this->translations;
    }

    public function setTranslations(EntitySearchResult $translations): void
    {
        $this->translations = $translations;
    }
}
