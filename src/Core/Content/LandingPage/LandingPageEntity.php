<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\Tag\TagCollection;

#[Package('buyers-experience')]
class LandingPageEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $active;

    /**
     * @var LandingPageTranslationCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $translations;

    /**
     * @var TagCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $tags;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $cmsPageId;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $cmsPageVersionId;

    /**
     * @var CmsPageEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $cmsPage;

    /**
     * @var SalesChannelCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $salesChannels;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $metaTitle;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $metaDescription;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $keywords;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $url;

    /**
     * @var array|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $slotConfig;

    /**
     * @var SeoUrlCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $seoUrls;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getTranslations(): ?LandingPageTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(LandingPageTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getTags(): ?TagCollection
    {
        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
    }

    public function getCmsPageId(): ?string
    {
        return $this->cmsPageId;
    }

    public function setCmsPageId(?string $cmsPageId): void
    {
        $this->cmsPageId = $cmsPageId;
    }

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getSlotConfig(): ?array
    {
        return $this->slotConfig;
    }

    public function setSlotConfig(?array $slotConfig): void
    {
        $this->slotConfig = $slotConfig;
    }

    public function getSeoUrls(): ?SeoUrlCollection
    {
        return $this->seoUrls;
    }

    public function setSeoUrls(SeoUrlCollection $seoUrls): void
    {
        $this->seoUrls = $seoUrls;
    }

    public function getCmsPageVersionId(): ?string
    {
        return $this->cmsPageVersionId;
    }

    public function setCmsPageVersionId(?string $cmsPageVersionId): void
    {
        $this->cmsPageVersionId = $cmsPageVersionId;
    }
}
