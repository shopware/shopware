<?php declare(strict_types=1);

namespace Shopware\Category\Struct;

use Shopware\Api\Entity\Entity;
use Shopware\Media\Struct\MediaBasicStruct;

class CategoryBasicStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $parentUuid;

    /**
     * @var string|null
     */
    protected $mediaUuid;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $path;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var int
     */
    protected $level;

    /**
     * @var string|null
     */
    protected $template;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var bool
     */
    protected $isBlog;

    /**
     * @var string|null
     */
    protected $external;

    /**
     * @var bool
     */
    protected $hideFilter;

    /**
     * @var bool
     */
    protected $hideTop;

    /**
     * @var string|null
     */
    protected $productBoxLayout;

    /**
     * @var string|null
     */
    protected $productStreamUuid;

    /**
     * @var bool
     */
    protected $hideSortings;

    /**
     * @var string|null
     */
    protected $sortingUuids;

    /**
     * @var string|null
     */
    protected $facetUuids;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var string|null
     */
    protected $pathNames;

    /**
     * @var string|null
     */
    protected $metaKeywords;

    /**
     * @var string|null
     */
    protected $metaTitle;

    /**
     * @var string|null
     */
    protected $metaDescription;

    /**
     * @var string|null
     */
    protected $cmsHeadline;

    /**
     * @var string|null
     */
    protected $cmsDescription;

    /**
     * @var MediaBasicStruct|null
     */
    protected $media;

    public function getParentUuid(): ?string
    {
        return $this->parentUuid;
    }

    public function setParentUuid(?string $parentUuid): void
    {
        $this->parentUuid = $parentUuid;
    }

    public function getMediaUuid(): ?string
    {
        return $this->mediaUuid;
    }

    public function setMediaUuid(?string $mediaUuid): void
    {
        $this->mediaUuid = $mediaUuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): void
    {
        $this->template = $template;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getIsBlog(): bool
    {
        return $this->isBlog;
    }

    public function setIsBlog(bool $isBlog): void
    {
        $this->isBlog = $isBlog;
    }

    public function getExternal(): ?string
    {
        return $this->external;
    }

    public function setExternal(?string $external): void
    {
        $this->external = $external;
    }

    public function getHideFilter(): bool
    {
        return $this->hideFilter;
    }

    public function setHideFilter(bool $hideFilter): void
    {
        $this->hideFilter = $hideFilter;
    }

    public function getHideTop(): bool
    {
        return $this->hideTop;
    }

    public function setHideTop(bool $hideTop): void
    {
        $this->hideTop = $hideTop;
    }

    public function getProductBoxLayout(): ?string
    {
        return $this->productBoxLayout;
    }

    public function setProductBoxLayout(?string $productBoxLayout): void
    {
        $this->productBoxLayout = $productBoxLayout;
    }

    public function getProductStreamUuid(): ?string
    {
        return $this->productStreamUuid;
    }

    public function setProductStreamUuid(?string $productStreamUuid): void
    {
        $this->productStreamUuid = $productStreamUuid;
    }

    public function getHideSortings(): bool
    {
        return $this->hideSortings;
    }

    public function setHideSortings(bool $hideSortings): void
    {
        $this->hideSortings = $hideSortings;
    }

    public function getSortingUuids(): ?string
    {
        return $this->sortingUuids;
    }

    public function setSortingUuids(?string $sortingUuids): void
    {
        $this->sortingUuids = $sortingUuids;
    }

    public function getFacetUuids(): ?string
    {
        return $this->facetUuids;
    }

    public function setFacetUuids(?string $facetUuids): void
    {
        $this->facetUuids = $facetUuids;
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

    public function getPathNames(): ?string
    {
        return $this->pathNames;
    }

    public function setPathNames(?string $pathNames): void
    {
        $this->pathNames = $pathNames;
    }

    public function getMetaKeywords(): ?string
    {
        return $this->metaKeywords;
    }

    public function setMetaKeywords(?string $metaKeywords): void
    {
        $this->metaKeywords = $metaKeywords;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function getCmsHeadline(): ?string
    {
        return $this->cmsHeadline;
    }

    public function setCmsHeadline(?string $cmsHeadline): void
    {
        $this->cmsHeadline = $cmsHeadline;
    }

    public function getCmsDescription(): ?string
    {
        return $this->cmsDescription;
    }

    public function setCmsDescription(?string $cmsDescription): void
    {
        $this->cmsDescription = $cmsDescription;
    }

    public function getMedia(): ?MediaBasicStruct
    {
        return $this->media;
    }

    public function setMedia(?MediaBasicStruct $media): void
    {
        $this->media = $media;
    }

    public function getPathArray(): array
    {
        return array_filter(explode('|', (string) $this->path));
    }
}
