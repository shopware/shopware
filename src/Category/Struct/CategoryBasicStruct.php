<?php declare(strict_types=1);
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
use Shopware\SeoUrl\Struct\SeoUrlBasicStruct;

class CategoryBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string|null
     */
    protected $parentUuid;

    /**
     * @var array
     */
    protected $path;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int|null
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
     * @var int|null
     */
    protected $mediaId;

    /**
     * @var string
     */
    protected $mediaUuid;

    /**
     * @var string|null
     */
    protected $productBoxLayout;

    /**
     * @var int|null
     */
    protected $productStreamId;

    /**
     * @var string|null
     */
    protected $productStreamUuid;

    /**
     * @var bool
     */
    protected $hideSortings;

    /**
     * @var array
     */
    protected $sortingIds;

    /**
     * @var array
     */
    protected $facetIds;

    /**
     * @var SeoUrlBasicStruct|null
     */
    protected $canonicalUrl;

    /**
     * @var CategoryBasicStruct[]
     */
    protected $children = [];

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getParentUuid(): ?string
    {
        return $this->parentUuid;
    }

    public function setParentUuid(?string $parentUuid): void
    {
        $this->parentUuid = $parentUuid;
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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
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

    public function getMediaId(): ?int
    {
        return $this->mediaId;
    }

    public function setMediaId(?int $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getMediaUuid(): string
    {
        return $this->mediaUuid;
    }

    public function setMediaUuid(string $mediaUuid): void
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

    public function getProductStreamId(): ?int
    {
        return $this->productStreamId;
    }

    public function setProductStreamId(?int $productStreamId): void
    {
        $this->productStreamId = $productStreamId;
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

    public function getCanonicalUrl(): ?SeoUrlBasicStruct
    {
        return $this->canonicalUrl;
    }

    public function setCanonicalUrl(?SeoUrlBasicStruct $canonicalUrl): void
    {
        $this->canonicalUrl = $canonicalUrl;
    }

    public function setChildren(array $children)
    {
        $this->children = $children;
    }

    public function getChildren(): array
    {
        return $this->children;
    }
}
