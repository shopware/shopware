<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryTranslation;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageEntity;

#[Package('discovery')]
class CategoryTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $categoryId;

    protected string $categoryVersionId;

    protected ?string $name = null;

    /**
     * @var array<string>|null
     */
    protected ?array $breadcrumb = null;

    protected ?CategoryEntity $category = null;

    protected ?LanguageEntity $language = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $slotConfig = null;

    protected ?string $linkType = null;

    protected ?bool $linkNewTab = null;

    protected ?string $internalLink = null;

    protected ?string $externalLink = null;

    protected ?string $description = null;

    protected ?string $metaTitle = null;

    protected ?string $metaDescription = null;

    protected ?string $keywords = null;

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function setCategoryId(string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCategory(): ?CategoryEntity
    {
        return $this->category;
    }

    public function setCategory(CategoryEntity $category): void
    {
        $this->category = $category;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSlotConfig(): ?array
    {
        return $this->slotConfig;
    }

    /**
     * @param array<string, mixed> $slotConfig
     */
    public function setSlotConfig(array $slotConfig): void
    {
        $this->slotConfig = $slotConfig;
    }

    public function getLinkType(): ?string
    {
        return $this->linkType;
    }

    public function setLinkType(?string $linkType): void
    {
        $this->linkType = $linkType;
    }

    public function getLinkNewTab(): ?bool
    {
        return $this->linkNewTab;
    }

    public function setLinkNewTab(?bool $linkNewTab): void
    {
        $this->linkNewTab = $linkNewTab;
    }

    public function getInternalLink(): ?string
    {
        return $this->internalLink;
    }

    public function setInternalLink(?string $internalLink): void
    {
        $this->internalLink = $internalLink;
    }

    public function getExternalLink(): ?string
    {
        return $this->externalLink;
    }

    public function setExternalLink(string $externalLink): void
    {
        $this->externalLink = $externalLink;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return string[]|null
     */
    public function getBreadcrumb(): ?array
    {
        return $this->breadcrumb;
    }

    /**
     * @param array<string>|null $breadcrumb
     */
    public function setBreadcrumb(?array $breadcrumb): void
    {
        $this->breadcrumb = $breadcrumb;
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

    public function getCategoryVersionId(): string
    {
        return $this->categoryVersionId;
    }

    public function setCategoryVersionId(string $categoryVersionId): void
    {
        $this->categoryVersionId = $categoryVersionId;
    }
}
