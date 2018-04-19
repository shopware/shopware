<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Category\Struct;

use Shopware\Framework\Struct\Struct;
use Shopware\SeoUrl\Struct\SeoUrl;

class CategoryIdentity extends Struct
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var int|null
     */
    protected $parent;

    /**
     * @var int[]
     */
    protected $path;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var int
     */
    protected $level;

    /**
     * @var \DateTime
     */
    protected $added;

    /**
     * @var \DateTime
     */
    protected $changedAt;

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
    protected $blog;

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
     * @var int|null
     */
    protected $mediaId;

    /**
     * @var string|null
     */
    protected $mediaUuid;

    /**
     * @var string|null
     */
    protected $productBoxLayout;

    /**
     * @var int|null
     */
    protected $streamId;

    /**
     * @var bool
     */
    protected $hideSortings;

    /**
     * @var int[]
     */
    protected $sortingIds;

    /**
     * @var int[]
     */
    protected $facetIds;

    /**
     * @var SeoUrl|null
     */
    protected $seoUrl;

    /**
     * @var bool
     */
    protected $isShopCategory;

    /**
     * Only filled by CategoryCollection::getTree
     * @var CategoryIdentity[]
     */
    protected $children = [];

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getParent(): ?int
    {
        return $this->parent;
    }

    public function setParent(?int$parent): void
    {
        $this->parent = $parent;
    }

    public function getPath(): array
    {
        return $this->path;
    }

    public function setPath(array $path): void
    {
        $this->path = $path;
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

    public function isShopCategory(): bool
    {
        return $this->isShopCategory;
    }

    public function setIsShopCategory(bool $isShopCategory): void
    {
        $this->isShopCategory = $isShopCategory;
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

    public function getAdded(): \DateTime
    {
        return $this->added;
    }

    public function setAdded(\DateTime $added): void
    {
        $this->added = $added;
    }

    public function getChangedAt(): \DateTime
    {
        return $this->changedAt;
    }

    public function setChangedAt(\DateTime $changedAt): void
    {
        $this->changedAt = $changedAt;
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

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): void
    {
        $this->template = $template;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function isBlog(): bool
    {
        return $this->blog;
    }

    public function setBlog(bool $blog): void
    {
        $this->blog = $blog;
    }

    public function getExternal(): ?string
    {
        return $this->external;
    }

    public function setExternal(?string $external): void
    {
        $this->external = $external;
    }

    public function isHideFilter(): bool
    {
        return $this->hideFilter;
    }

    public function setHideFilter(bool $hideFilter): void
    {
        $this->hideFilter = $hideFilter;
    }

    public function isHideTop(): bool
    {
        return $this->hideTop;
    }

    public function setHideTop(bool $hideTop): void
    {
        $this->hideTop = $hideTop;
    }

    public function getMediaId(): ?int
    {
        return $this->mediaId;
    }

    public function setMediaId(?int $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getMediaUuid(): ?string
    {
        return $this->mediaUuid;
    }

    public function setMediaUuid(?string $mediaUuid): void
    {
        $this->mediaUuid = $mediaUuid;
    }

    public function getProductBoxLayout(): ?string
    {
        return $this->productBoxLayout;
    }

    public function setProductBoxLayout(?string $productBoxLayout): void
    {
        $this->productBoxLayout = $productBoxLayout;
    }

    public function getStreamId(): ?int
    {
        return $this->streamId;
    }

    public function setStreamId(?int $streamId): void
    {
        $this->streamId = $streamId;
    }

    public function isHideSortings(): bool
    {
        return $this->hideSortings;
    }

    public function setHideSortings(bool $hideSortings): void
    {
        $this->hideSortings = $hideSortings;
    }

    public function getSortingIds(): array
    {
        return $this->sortingIds;
    }

    public function setSortingIds(array $sortingIds): void
    {
        $this->sortingIds = $sortingIds;
    }

    public function getFacetIds(): array
    {
        return $this->facetIds;
    }

    public function setFacetIds(array $facetIds): void
    {
        $this->facetIds = $facetIds;
    }


    public function getSeoUrl(): ?SeoUrl
    {
        return $this->seoUrl;
    }

    public function setSeoUrl(?SeoUrl $seoUrl): void
    {
        $this->seoUrl = $seoUrl;
    }

    /**
     * @return CategoryIdentity[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @param CategoryIdentity[] $children
     */
    public function setChildren(array $children): void
    {
        $this->children = $children;
    }
}
