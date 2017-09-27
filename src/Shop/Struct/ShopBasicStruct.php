<?php declare(strict_types=1);

namespace Shopware\Shop\Struct;

use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Framework\Struct\Struct;
use Shopware\Locale\Struct\LocaleBasicStruct;

class ShopBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $title;

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
     * @var string|null
     */
    protected $parentUuid;

    /**
     * @var string
     */
    protected $templateUuid;

    /**
     * @var string
     */
    protected $documentTemplateUuid;

    /**
     * @var string
     */
    protected $categoryUuid;

    /**
     * @var string
     */
    protected $localeUuid;

    /**
     * @var string
     */
    protected $currencyUuid;

    /**
     * @var string
     */
    protected $customerGroupUuid;

    /**
     * @var string|null
     */
    protected $fallbackLocaleUuid;

    /**
     * @var string|null
     */
    protected $paymentMethodUuid;

    /**
     * @var string|null
     */
    protected $shippingMethodUuid;

    /**
     * @var string|null
     */
    protected $areaCountryUuid;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var CurrencyBasicStruct
     */
    protected $currency;

    /**
     * @var LocaleBasicStruct
     */
    protected $locale;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
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

    public function setBasePath(?string $basePath): void
    {
        $this->basePath = rtrim((string) $basePath, '/') . '/';
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(?string $baseUrl): void
    {
        $this->baseUrl = rtrim((string) $baseUrl, '/') . '/';
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

    public function getParentUuid(): ?string
    {
        return $this->parentUuid;
    }

    public function setParentUuid(?string $parentUuid): void
    {
        $this->parentUuid = $parentUuid;
    }

    public function getTemplateUuid(): string
    {
        return $this->templateUuid;
    }

    public function setTemplateUuid(string $templateUuid): void
    {
        $this->templateUuid = $templateUuid;
    }

    public function getDocumentTemplateUuid(): string
    {
        return $this->documentTemplateUuid;
    }

    public function setDocumentTemplateUuid(string $documentTemplateUuid): void
    {
        $this->documentTemplateUuid = $documentTemplateUuid;
    }

    public function getCategoryUuid(): string
    {
        return $this->categoryUuid;
    }

    public function setCategoryUuid(string $categoryUuid): void
    {
        $this->categoryUuid = $categoryUuid;
    }

    public function getLocaleUuid(): string
    {
        return $this->localeUuid;
    }

    public function setLocaleUuid(string $localeUuid): void
    {
        $this->localeUuid = $localeUuid;
    }

    public function getCurrencyUuid(): string
    {
        return $this->currencyUuid;
    }

    public function setCurrencyUuid(string $currencyUuid): void
    {
        $this->currencyUuid = $currencyUuid;
    }

    public function getCustomerGroupUuid(): string
    {
        return $this->customerGroupUuid;
    }

    public function setCustomerGroupUuid(string $customerGroupUuid): void
    {
        $this->customerGroupUuid = $customerGroupUuid;
    }

    public function getFallbackLocaleUuid(): ?string
    {
        return $this->fallbackLocaleUuid;
    }

    public function setFallbackLocaleUuid(?string $fallbackLocaleUuid): void
    {
        $this->fallbackLocaleUuid = $fallbackLocaleUuid;
    }

    public function getPaymentMethodUuid(): ?string
    {
        return $this->paymentMethodUuid;
    }

    public function setPaymentMethodUuid(?string $paymentMethodUuid): void
    {
        $this->paymentMethodUuid = $paymentMethodUuid;
    }

    public function getShippingMethodUuid(): ?string
    {
        return $this->shippingMethodUuid;
    }

    public function setShippingMethodUuid(?string $shippingMethodUuid): void
    {
        $this->shippingMethodUuid = $shippingMethodUuid;
    }

    public function getAreaCountryUuid(): ?string
    {
        return $this->areaCountryUuid;
    }

    public function setAreaCountryUuid(?string $areaCountryUuid): void
    {
        $this->areaCountryUuid = $areaCountryUuid;
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

    public function getCurrency(): CurrencyBasicStruct
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyBasicStruct $currency): void
    {
        $this->currency = $currency;
    }

    public function getLocale(): LocaleBasicStruct
    {
        return $this->locale;
    }

    public function setLocale(LocaleBasicStruct $locale): void
    {
        $this->locale = $locale;
    }
}
