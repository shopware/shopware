<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrl;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SeoUrlEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string|null
     */
    protected $salesChannelId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $routeName;

    /**
     * @var string
     */
    protected $foreignKey;

    /**
     * @var string
     */
    protected $pathInfo;

    /**
     * @var string
     */
    protected $seoPathInfo;

    /**
     * @var bool
     */
    protected $isCanonical;

    /**
     * @var bool
     */
    protected $isModified;

    /**
     * @var bool
     */
    protected $isDeleted;

    /**
     * @var bool
     */
    protected $isValid;

    /**
     * @var SalesChannelEntity|null
     */
    protected $salesChannel;

    /**
     * @var LanguageEntity|null
     */
    protected $language;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string|null
     */
    protected $error;

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function setRouteName(string $routeName): void
    {
        $this->routeName = $routeName;
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function setForeignKey(string $foreignKey): void
    {
        $this->foreignKey = $foreignKey;
    }

    public function getPathInfo(): string
    {
        return $this->pathInfo;
    }

    public function setPathInfo(string $pathInfo): void
    {
        $this->pathInfo = $pathInfo;
    }

    public function getSeoPathInfo(): string
    {
        return $this->seoPathInfo;
    }

    public function setSeoPathInfo(string $seoPathInfo): void
    {
        $this->seoPathInfo = $seoPathInfo;
    }

    public function getIsCanonical(): ?bool
    {
        return $this->isCanonical;
    }

    public function setIsCanonical(?bool $isCanonical): void
    {
        $this->isCanonical = $isCanonical;
    }

    public function getIsModified(): bool
    {
        return $this->isModified;
    }

    public function setIsModified(bool $isModified): void
    {
        $this->isModified = $isModified;
    }

    public function getIsDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): void
    {
        $this->isDeleted = $isDeleted;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): void
    {
        $this->error = $error;
    }
}
