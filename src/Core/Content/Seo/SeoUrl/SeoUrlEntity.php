<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrl;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('buyers-experience')]
class SeoUrlEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $salesChannelId;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $languageId;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $routeName;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $foreignKey;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $pathInfo;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $seoPathInfo;

    /**
     * @var bool|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $isCanonical;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $isModified;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $isDeleted;

    /**
     * @var SalesChannelEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $salesChannel;

    /**
     * @var LanguageEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $language;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $url;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
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

    /**
     * The error property will be set in the runtime and is not a field in the seo_url table.
     * It is used for the url generation in a json serialized entity.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * The error property will be set in the runtime and is not a field in the seo_url table.
     * It is used for the url generation in a json serialized entity.
     */
    public function setError(?string $error): void
    {
        $this->error = $error;
    }
}
