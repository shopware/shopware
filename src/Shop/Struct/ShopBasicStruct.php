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

namespace Shopware\Shop\Struct;

use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Framework\Struct\Struct;
use Shopware\Locale\Struct\LocaleBasicStruct;

class ShopBasicStruct extends Struct
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
     * @var string|null
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
     * @var string
     */
    protected $hosts;

    /**
     * @var bool
     */
    protected $secure;

    /**
     * @var int|null
     */
    protected $templateId;

    /**
     * @var int|null
     */
    protected $documentTemplateId;

    /**
     * @var int|null
     */
    protected $categoryId;

    /**
     * @var int|null
     */
    protected $localeId;

    /**
     * @var int|null
     */
    protected $currencyId;

    /**
     * @var int|null
     */
    protected $customerGroupId;

    /**
     * @var int|null
     */
    protected $fallbackId;

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
     * @var int
     */
    protected $paymentMethodId;

    /**
     * @var int
     */
    protected $shippingMethodId;

    /**
     * @var int
     */
    protected $areaCountryId;

    /**
     * @var string
     */
    protected $taxCalculationType;

    /**
     * @var string|null
     */
    protected $mainUuid;

    /**
     * @var string|null
     */
    protected $templateUuid;

    /**
     * @var string|null
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
     * @var string
     */
    protected $paymentMethodUuid;

    /**
     * @var string
     */
    protected $shippingMethodUuid;

    /**
     * @var string
     */
    protected $areaCountryUuid;

    /**
     * @var CurrencyBasicStruct
     */
    protected $currency;

    /**
     * @var LocaleBasicStruct
     */
    protected $locale;

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

    public function getMainId(): ?int
    {
        return $this->mainId;
    }

    public function setMainId(?int $mainId): void
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

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function setHost(?string $host): void
    {
        $this->host = $host;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function setBasePath(string $basePath): void
    {
        $basePath = rtrim($basePath, '/').'/';
        $this->basePath = $basePath;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $baseUrl = rtrim($baseUrl, '/').'/';
        $this->baseUrl = $baseUrl;
    }

    public function getHosts(): string
    {
        return $this->hosts;
    }

    public function setHosts(string $hosts): void
    {
        $this->hosts = $hosts;
    }

    public function getSecure(): bool
    {
        return $this->secure;
    }

    public function setSecure(bool $secure): void
    {
        $this->secure = $secure;
    }

    public function getTemplateId(): ?int
    {
        return $this->templateId;
    }

    public function setTemplateId(?int $templateId): void
    {
        $this->templateId = $templateId;
    }

    public function getDocumentTemplateId(): ?int
    {
        return $this->documentTemplateId;
    }

    public function setDocumentTemplateId(?int $documentTemplateId): void
    {
        $this->documentTemplateId = $documentTemplateId;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function setCategoryId(?int $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function getLocaleId(): ?int
    {
        return $this->localeId;
    }

    public function setLocaleId(?int $localeId): void
    {
        $this->localeId = $localeId;
    }

    public function getCurrencyId(): ?int
    {
        return $this->currencyId;
    }

    public function setCurrencyId(?int $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getCustomerGroupId(): ?int
    {
        return $this->customerGroupId;
    }

    public function setCustomerGroupId(?int $customerGroupId): void
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

    public function getPaymentMethodId(): int
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(int $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getShippingMethodId(): int
    {
        return $this->shippingMethodId;
    }

    public function setShippingMethodId(int $shippingMethodId): void
    {
        $this->shippingMethodId = $shippingMethodId;
    }

    public function getAreaCountryId(): int
    {
        return $this->areaCountryId;
    }

    public function setAreaCountryId(int $areaCountryId): void
    {
        $this->areaCountryId = $areaCountryId;
    }

    public function getTaxCalculationType(): string
    {
        return $this->taxCalculationType;
    }

    public function setTaxCalculationType(string $taxCalculationType): void
    {
        $this->taxCalculationType = $taxCalculationType;
    }

    public function getMainUuid(): ?string
    {
        return $this->mainUuid;
    }

    public function setMainUuid(?string $mainUuid): void
    {
        $this->mainUuid = $mainUuid;
    }

    public function getTemplateUuid(): ?string
    {
        return $this->templateUuid;
    }

    public function setTemplateUuid(?string $templateUuid): void
    {
        $this->templateUuid = $templateUuid;
    }

    public function getDocumentTemplateUuid(): ?string
    {
        return $this->documentTemplateUuid;
    }

    public function setDocumentTemplateUuid(?string $documentTemplateUuid): void
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

    public function getPaymentMethodUuid(): string
    {
        return $this->paymentMethodUuid;
    }

    public function setPaymentMethodUuid(string $paymentMethodUuid): void
    {
        $this->paymentMethodUuid = $paymentMethodUuid;
    }

    public function getShippingMethodUuid(): string
    {
        return $this->shippingMethodUuid;
    }

    public function setShippingMethodUuid(string $shippingMethodUuid): void
    {
        $this->shippingMethodUuid = $shippingMethodUuid;
    }

    public function getAreaCountryUuid(): string
    {
        return $this->areaCountryUuid;
    }

    public function setAreaCountryUuid(string $areaCountryUuid): void
    {
        $this->areaCountryUuid = $areaCountryUuid;
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
