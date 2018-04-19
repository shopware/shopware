<?php

namespace Shopware\Shop\Struct;

use Shopware\Currency\Struct\Currency;
use Shopware\Framework\Struct\Struct;
use Shopware\Locale\Struct\Locale;

class ShopIdentity extends Struct
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
     * @var int
     */
    protected $mainId;

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
     * @var string[]
     */
    protected $hosts;

    /**
     * @var bool
     */
    protected $secure;

    /**
     * @var int
     */
    protected $templateId;

    /**
     * @var int
     */
    protected $documentTemplateId;

    /**
     * @var int
     */
    protected $categoryId;

    /**
     * @var int
     */
    protected $customerGroupId;

    /**
     * @var int|null
     */
    protected $fallbackId;

    /**
     * @var int
     */
    protected $customerScope;

    /**
     * @var bool
     */
    protected $default;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var int
     */
    protected $paymentId;

    /**
     * @var int
     */
    protected $dispatchId;

    /**
     * @var int
     */
    protected $countryId;

    /**
     * @var string
     */
    protected $taxCalculationType;

    /**
     * @var Locale
     */
    protected $locale;

    /**
     * @var Currency
     */
    protected $currency;

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

    public function getMainId(): int
    {
        return $this->mainId;
    }

    public function setMainId(int $mainId): void
    {
        $this->mainId = $mainId;
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

    public function getHosts(): array
    {
        return $this->hosts;
    }

    public function setHosts(array $hosts): void
    {
        $this->hosts = $hosts;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function setSecure(bool $secure): void
    {
        $this->secure = $secure;
    }

    public function getTemplateId(): int
    {
        return $this->templateId;
    }

    public function setTemplateId(int $templateId): void
    {
        $this->templateId = $templateId;
    }

    public function getDocumentTemplateId(): int
    {
        return $this->documentTemplateId;
    }

    public function setDocumentTemplateId(int $documentTemplateId): void
    {
        $this->documentTemplateId = $documentTemplateId;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function setCategoryId(int $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function getCustomerGroupId(): int
    {
        return $this->customerGroupId;
    }

    public function setCustomerGroupId(int $customerGroupId): void
    {
        $this->customerGroupId = $customerGroupId;
    }

    public function getFallbackId(): ?int
    {
        return $this->fallbackId;
    }

    public function setFallbackId(?int $fallbackId): void
    {
        $this->fallbackId = $fallbackId;
    }

    public function getCustomerScope(): int
    {
        return $this->customerScope;
    }

    public function setCustomerScope(int $customerScope): void
    {
        $this->customerScope = $customerScope;
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    public function setDefault(bool $default): void
    {
        $this->default = $default;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getPaymentId(): int
    {
        return $this->paymentId;
    }

    public function setPaymentId(int $paymentId): void
    {
        $this->paymentId = $paymentId;
    }

    public function getDispatchId(): int
    {
        return $this->dispatchId;
    }

    public function setDispatchId(int $dispatchId): void
    {
        $this->dispatchId = $dispatchId;
    }

    public function getCountryId(): int
    {
        return $this->countryId;
    }

    public function setCountryId(int $countryId): void
    {
        $this->countryId = $countryId;
    }

    public function getTaxCalculationType(): string
    {
        return $this->taxCalculationType;
    }

    public function setTaxCalculationType(string $taxCalculationType): void
    {
        $this->taxCalculationType = $taxCalculationType;
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function setLocale(Locale $locale): void
    {
        $this->locale = $locale;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): void
    {
        $this->currency = $currency;
    }
}