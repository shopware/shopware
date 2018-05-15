<?php declare(strict_types=1);

namespace Shopware\Content\Category\Struct;

use Shopware\Framework\ORM\Entity;

class CategoryBasicStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var string|null
     */
    protected $mediaId;

    /**
     * @var string|null
     */
    protected $productStreamId;

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
     * @var bool
     */
    protected $hideSortings;

    /**
     * @var string|null
     */
    protected $sortingIds;

    /**
     * @var string|null
     */
    protected $facetIds;

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

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getProductStreamId(): ?string
    {
        return $this->productStreamId;
    }

    public function setProductStreamId(?string $productStreamId): void
    {
        $this->productStreamId = $productStreamId;
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

    public function getHideSortings(): bool
    {
        return $this->hideSortings;
    }

    public function setHideSortings(bool $hideSortings): void
    {
        $this->hideSortings = $hideSortings;
    }

    public function getSortingIds(): ?string
    {
        return $this->sortingIds;
    }

    public function setSortingIds(?string $sortingIds): void
    {
        $this->sortingIds = $sortingIds;
    }

    public function getFacetIds(): ?string
    {
        return $this->facetIds;
    }

    public function setFacetIds(?string $facetIds): void
    {
        $this->facetIds = $facetIds;
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

    public function getPathArray(): array
    {
        return array_filter(explode('|', (string) $this->path));
    }

    public function getPathNamesArray(): array
    {
        return array_filter(explode('|', (string) $this->pathNames));
    }
}
