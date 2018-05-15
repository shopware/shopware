<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Struct;

use Shopware\System\Currency\Struct\CurrencyBasicStruct;
use Shopware\Framework\ORM\Entity;
use Shopware\System\Locale\Struct\LocaleBasicStruct;

class ShopBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $templateId;

    /**
     * @var string
     */
    protected $documentTemplateId;

    /**
     * @var string
     */
    protected $categoryId;

    /**
     * @var string
     */
    protected $localeId;

    /**
     * @var string
     */
    protected $currencyId;

    /**
     * @var string
     */
    protected $customerGroupId;

    /**
     * @var string|null
     */
    protected $fallbackTranslationId;

    /**
     * @var string
     */
    protected $paymentMethodId;

    /**
     * @var string
     */
    protected $shippingMethodId;

    /**
     * @var string
     */
    protected $countryId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var string|null
     */
    protected $title;

    /**
     * @var string|null
     */
    protected $hosts;

    /**
     * @var bool
     */
    protected $isSecure;

    /**
     * @var bool
     */
    protected $customerScope;

    /**
     * @var bool
     */
    protected $isDefault;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var string
     */
    protected $taxCalculationType;

    /**
     * @var string[]
     */
    protected $catalogIds = [];

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var LocaleBasicStruct
     */
    protected $locale;

    /**
     * @var CurrencyBasicStruct
     */
    protected $currency;

    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    public function setTemplateId(string $templateId): void
    {
        $this->templateId = $templateId;
    }

    public function getDocumentTemplateId(): string
    {
        return $this->documentTemplateId;
    }

    public function setDocumentTemplateId(string $documentTemplateId): void
    {
        $this->documentTemplateId = $documentTemplateId;
    }

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function setCategoryId(string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function getLocaleId(): string
    {
        return $this->localeId;
    }

    public function setLocaleId(string $localeId): void
    {
        $this->localeId = $localeId;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getCustomerGroupId(): string
    {
        return $this->customerGroupId;
    }

    public function setCustomerGroupId(string $customerGroupId): void
    {
        $this->customerGroupId = $customerGroupId;
    }

    public function getFallbackTranslationId(): ?string
    {
        return $this->fallbackTranslationId;
    }

    public function setFallbackTranslationId(?string $fallbackTranslationId): void
    {
        $this->fallbackTranslationId = $fallbackTranslationId;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getShippingMethodId(): string
    {
        return $this->shippingMethodId;
    }

    public function setShippingMethodId(string $shippingMethodId): void
    {
        $this->shippingMethodId = $shippingMethodId;
    }

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function setCountryId(string $countryId): void
    {
        $this->countryId = $countryId;
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

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function setBasePath(string $basePath): void
    {
        $this->basePath = $basePath;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getHosts(): ?string
    {
        return $this->hosts;
    }

    public function setHosts(?string $hosts): void
    {
        $this->hosts = $hosts;
    }

    public function getIsSecure(): bool
    {
        return $this->isSecure;
    }

    public function setIsSecure(bool $isSecure): void
    {
        $this->isSecure = $isSecure;
    }

    public function getCustomerScope(): bool
    {
        return $this->customerScope;
    }

    public function setCustomerScope(bool $customerScope): void
    {
        $this->customerScope = $customerScope;
    }

    public function getIsDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getTaxCalculationType(): string
    {
        return $this->taxCalculationType;
    }

    public function setTaxCalculationType(string $taxCalculationType): void
    {
        $this->taxCalculationType = $taxCalculationType;
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

    public function getLocale(): LocaleBasicStruct
    {
        return $this->locale;
    }

    public function setLocale(LocaleBasicStruct $locale): void
    {
        $this->locale = $locale;
    }

    public function getCurrency(): CurrencyBasicStruct
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyBasicStruct $currency): void
    {
        $this->currency = $currency;
    }

    /**
     * @return string[]
     */
    public function getCatalogIds(): array
    {
        return $this->catalogIds;
    }

    /**
     * @param string[] $catalogIds
     */
    public function setCatalogIds(array $catalogIds): void
    {
        $this->catalogIds = $catalogIds;
    }
}
