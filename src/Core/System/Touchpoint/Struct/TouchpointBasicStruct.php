<?php declare(strict_types=1);

namespace Shopware\Core\System\Touchpoint\Struct;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Currency\Struct\CurrencyBasicStruct;
use Shopware\Core\System\Language\Struct\LanguageBasicStruct;

class TouchpointBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $currencyId;

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
    protected $type;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $accessKey;

    /**
     * @var string
     */
    protected $secretAccessKey;

    /**
     * @var array
     */
    protected $catalogIds = [];

    /**
     * @var array
     */
    protected $currencyIds;

    /**
     * @var array
     */
    protected $languageIds;

    /**
     * @var array|null
     */
    protected $configuration;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var string
     */
    protected $taxCalculationType;

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
     * @var LanguageBasicStruct
     */
    protected $language;

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    public function setAccessKey(string $accessKey): void
    {
        $this->accessKey = $accessKey;
    }

    public function getSecretAccessKey(): string
    {
        return $this->secretAccessKey;
    }

    public function setSecretAccessKey(string $secretAccessKey): void
    {
        $this->secretAccessKey = $secretAccessKey;
    }

    public function getCatalogIds(): array
    {
        return $this->catalogIds;
    }

    public function setCatalogIds(array $catalogIds): void
    {
        $this->catalogIds = $catalogIds;
    }

    public function getCurrencyIds(): array
    {
        return $this->currencyIds;
    }

    public function setCurrencyIds(array $currencyIds): void
    {
        $this->currencyIds = $currencyIds;
    }

    public function getLanguageIds(): array
    {
        return $this->languageIds;
    }

    public function setLanguageIds(array $languageIds): void
    {
        $this->languageIds = $languageIds;
    }

    public function getConfiguration(): ?array
    {
        return $this->configuration;
    }

    public function setConfiguration(?array $configuration): void
    {
        $this->configuration = $configuration;
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

    public function getCurrency(): CurrencyBasicStruct
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyBasicStruct $currency): void
    {
        $this->currency = $currency;
    }

    public function getLanguage(): LanguageBasicStruct
    {
        return $this->language;
    }

    /**
     * @param LanguageBasicStruct $language
     */
    public function setLanguage(LanguageBasicStruct $language): void
    {
        $this->language = $language;
    }
}
