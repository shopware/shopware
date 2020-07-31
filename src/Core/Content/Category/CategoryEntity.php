<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category;

use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\Tag\TagCollection;

class CategoryEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var int
     */
    protected $autoIncrement;

    /**
     * @var string|null
     */
    protected $mediaId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var array|null
     */
    protected $breadcrumb;

    /**
     * @var string|null
     */
    protected $path;

    /**
     * @var int
     */
    protected $level;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var int
     */
    protected $childCount;

    /**
     * @var bool
     */
    protected $displayNestedProducts;

    /**
     * @var CategoryEntity|null
     */
    protected $parent;

    /**
     * @var CategoryCollection|null
     */
    protected $children;

    /**
     * @var CategoryTranslationCollection|null
     */
    protected $translations;

    /**
     * @var MediaEntity|null
     */
    protected $media;

    /**
     * @var ProductCollection|null
     */
    protected $products;

    /**
     * @var ProductCollection|null
     */
    protected $nestedProducts;

    /**
     * @var string|null
     */
    protected $afterCategoryId;

    /**
     * @var array|null
     */
    protected $customFields;

    /**
     * @var TagCollection|null
     */
    protected $tags;

    /**
     * @var string|null
     */
    protected $cmsPageId;

    /**
     * @var CmsPageEntity|null
     */
    protected $cmsPage;

    /**
     * @var string|null
     */
    protected $productStreamId;

    /**
     * @var ProductStreamEntity|null
     */
    protected $productStream;

    /**
     * @var array|null
     */
    protected $slotConfig;

    /**
     * @var SalesChannelCollection|null
     */
    protected $navigationSalesChannels;

    /**
     * @var SalesChannelCollection|null
     */
    protected $footerSalesChannels;

    /**
     * @var SalesChannelCollection|null
     */
    protected $serviceSalesChannels;

    /**
     * @var string|null
     */
    protected $externalLink;

    /**
     * @var bool
     */
    protected $visible;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $productAssignmentType;

    /**
     * @var string|null
     */
    protected $description;

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
    protected $keywords;

    /**
     * @var MainCategoryCollection|null
     */
    protected $mainCategories;

    /**
     * @var SeoUrlCollection|null
     */
    protected $seoUrls;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
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

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getChildCount(): int
    {
        return $this->childCount;
    }

    public function setChildCount(int $childCount): void
    {
        $this->childCount = $childCount;
    }

    public function getParent(): ?CategoryEntity
    {
        return $this->parent;
    }

    public function setParent(CategoryEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getChildren(): ?CategoryCollection
    {
        return $this->children;
    }

    public function setChildren(CategoryCollection $children): void
    {
        $this->children = $children;
    }

    public function getTranslations(): ?CategoryTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(CategoryTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }

    public function getAutoIncrement(): int
    {
        return $this->autoIncrement;
    }

    public function setAutoIncrement(int $autoIncrement): void
    {
        $this->autoIncrement = $autoIncrement;
    }

    public function getNestedProducts(): ?ProductCollection
    {
        return $this->nestedProducts;
    }

    public function setNestedProducts(ProductCollection $nestedProducts): void
    {
        $this->nestedProducts = $nestedProducts;
    }

    public function getDisplayNestedProducts(): bool
    {
        return $this->displayNestedProducts;
    }

    public function setDisplayNestedProducts(bool $displayNestedProducts): void
    {
        $this->displayNestedProducts = $displayNestedProducts;
    }

    public function getAfterCategoryId(): ?string
    {
        return $this->afterCategoryId;
    }

    public function setAfterCategoryId(string $afterCategoryId): void
    {
        $this->afterCategoryId = $afterCategoryId;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getTags(): ?TagCollection
    {
        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
    }

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }

    public function getCmsPageId(): ?string
    {
        return $this->cmsPageId;
    }

    public function setCmsPageId(string $cmsPageId): void
    {
        $this->cmsPageId = $cmsPageId;
    }

    public function getProductStream(): ?ProductStreamEntity
    {
        return $this->productStream;
    }

    public function setProductStream(ProductStreamEntity $productStream): void
    {
        $this->productStream = $productStream;
    }

    public function getProductStreamId(): ?string
    {
        return $this->productStreamId;
    }

    public function setProductStreamId(string $productStreamId): void
    {
        $this->productStreamId = $productStreamId;
    }

    public function getSlotConfig(): ?array
    {
        return $this->slotConfig;
    }

    public function setSlotConfig(array $slotConfig): void
    {
        $this->slotConfig = $slotConfig;
    }

    public function getNavigationSalesChannels(): ?SalesChannelCollection
    {
        return $this->navigationSalesChannels;
    }

    public function setNavigationSalesChannels(SalesChannelCollection $navigationSalesChannels): void
    {
        $this->navigationSalesChannels = $navigationSalesChannels;
    }

    public function getFooterSalesChannels(): ?SalesChannelCollection
    {
        return $this->footerSalesChannels;
    }

    public function setFooterSalesChannels(SalesChannelCollection $footerSalesChannels): void
    {
        $this->footerSalesChannels = $footerSalesChannels;
    }

    public function getServiceSalesChannels(): ?SalesChannelCollection
    {
        return $this->serviceSalesChannels;
    }

    public function setServiceSalesChannels(SalesChannelCollection $serviceSalesChannels): void
    {
        $this->serviceSalesChannels = $serviceSalesChannels;
    }

    public function getExternalLink(): ?string
    {
        return $this->externalLink;
    }

    public function setExternalLink(string $externalLink): void
    {
        $this->externalLink = $externalLink;
    }

    public function getVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): void
    {
        $this->visible = $visible;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getBreadcrumb(): array
    {
        return array_values($this->getBreadcrumbMapping());
    }

    public function getPlainBreadcrumb(): array
    {
        return $this->getBreadcrumbMapping();
    }

    public function setBreadcrumb(?array $breadcrumb): void
    {
        $this->breadcrumb = $breadcrumb;
    }

    public function buildSeoBreadcrumb(?string $navigationCategoryId): ?array
    {
        $categoryBreadcrumb = $this->getBreadcrumbMapping();

        // If the current SalesChannel is null ( which refers to the default template SalesChannel) or
        // this category has no root, we return the full breadcrumb
        if ($navigationCategoryId === null) {
            return $categoryBreadcrumb;
        }

        // Root case
        if (count($categoryBreadcrumb) < 2) {
            return null;
        }

        // Check where this category is located in relation to the navigation entry point of the sales channel
        $salesChannelPos = array_search($navigationCategoryId, array_keys($categoryBreadcrumb), true);

        if ($salesChannelPos !== false) {
            // Remove all breadcrumbs preceding the navigation category
            return array_slice($categoryBreadcrumb, $salesChannelPos + 1);
        }

        return $categoryBreadcrumb;
    }

    public function jsonSerialize(): array
    {
        // Make sure that the sorted breadcrumb gets serialized
        $data = parent::jsonSerialize();
        $data['translated']['breadcrumb'] = $data['breadcrumb'] = $this->getBreadcrumb();

        return $data;
    }

    public function getMainCategories(): ?MainCategoryCollection
    {
        return $this->mainCategories;
    }

    public function setMainCategories(MainCategoryCollection $mainCategories): void
    {
        $this->mainCategories = $mainCategories;
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

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): void
    {
        $this->keywords = $keywords;
    }

    public function getSeoUrls(): ?SeoUrlCollection
    {
        return $this->seoUrls;
    }

    public function setSeoUrls(SeoUrlCollection $seoUrls): void
    {
        $this->seoUrls = $seoUrls;
    }

    public function getApiAlias(): string
    {
        return 'category';
    }

    public function getProductAssignmentType(): string
    {
        return $this->productAssignmentType;
    }

    public function setProductAssignmentType(string $productAssignmentType): void
    {
        $this->productAssignmentType = $productAssignmentType;
    }

    private function getBreadcrumbMapping(): array
    {
        $breadcrumb = $this->getTranslation('breadcrumb');
        if ($breadcrumb === null) {
            return [];
        }
        if ($this->path === null) {
            return $breadcrumb;
        }

        $parts = array_slice(explode('|', $this->path), 1, -1);

        $filtered = [];
        foreach ($parts as $id) {
            $filtered[$id] = $breadcrumb[$id];
        }

        $filtered[$this->getId()] = $breadcrumb[$this->getId()];

        return $filtered;
    }
}
