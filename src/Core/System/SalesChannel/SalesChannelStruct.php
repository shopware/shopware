<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodStruct;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodStruct;
use Shopware\Core\Content\Catalog\CatalogCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryStruct;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyStruct;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageStruct;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeStruct;

class SalesChannelStruct extends Entity
{
    use EntityIdTrait;
    /**
     * @var string
     */
    protected $typeId;

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
     * @var string|null
     */
    protected $name;

    /**
     * @var string
     */
    protected $accessKey;

    /**
     * @var CatalogCollection|null
     */
    protected $catalogs;

    /**
     * @var CurrencyCollection|null
     */
    protected $currencies;

    /**
     * @var LanguageCollection|null
     */
    protected $languages;

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
     * @var SalesChannelTypeStruct
     */
    protected $type;

    /**
     * @var CurrencyStruct
     */
    protected $currency;

    /**
     * @var LanguageStruct
     */
    protected $language;

    /**
     * @var PaymentMethodStruct|null
     */
    protected $paymentMethod;

    /**
     * @var ShippingMethodStruct|null
     */
    protected $shippingMethod;

    /**
     * @var CountryStruct|null
     */
    protected $country;

    /**
     * @var OrderCollection|null
     */
    protected $orders;

    /**
     * @var CustomerCollection|null
     */
    protected $customers;

    /**
     * @var CountryCollection|null
     */
    protected $countries;

    /**
     * @var PaymentMethodCollection|null
     */
    protected $paymentMethods;

    /**
     * @var ShippingMethodCollection|null
     */
    protected $shippingMethods;

    /**
     * @var SalesChannelTranslationCollection|null
     */
    protected $translations;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
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

    public function getCatalogs(): ?CatalogCollection
    {
        return $this->catalogs;
    }

    public function setCatalogs(CatalogCollection $catalogs): void
    {
        $this->catalogs = $catalogs;
    }

    public function getCurrencies(): ?CurrencyCollection
    {
        return $this->currencies;
    }

    public function setCurrencies(CurrencyCollection $currencies): void
    {
        $this->currencies = $currencies;
    }

    public function getLanguages(): ?LanguageCollection
    {
        return $this->languages;
    }

    public function setLanguages(LanguageCollection $languages): void
    {
        $this->languages = $languages;
    }

    public function getConfiguration(): ?array
    {
        return $this->configuration;
    }

    public function setConfiguration(array $configuration): void
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

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getCurrency(): CurrencyStruct
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyStruct $currency): void
    {
        $this->currency = $currency;
    }

    public function getLanguage(): LanguageStruct
    {
        return $this->language;
    }

    /**
     * @param LanguageStruct $language
     */
    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }

    public function getPaymentMethod(): ?PaymentMethodStruct
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodStruct $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getShippingMethod(): ?ShippingMethodStruct
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodStruct $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }

    public function getCountry(): ?CountryStruct
    {
        return $this->country;
    }

    public function setCountry(CountryStruct $country): void
    {
        $this->country = $country;
    }

    public function getOrders(): ?OrderCollection
    {
        return $this->orders;
    }

    public function setOrders(OrderCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getCustomers(): ?CustomerCollection
    {
        return $this->customers;
    }

    public function setCustomers(CustomerCollection $customers): void
    {
        $this->customers = $customers;
    }

    public function getTypeId(): string
    {
        return $this->typeId;
    }

    public function setTypeId(string $typeId): void
    {
        $this->typeId = $typeId;
    }

    public function getType(): SalesChannelTypeStruct
    {
        return $this->type;
    }

    public function setType(SalesChannelTypeStruct $type): void
    {
        $this->type = $type;
    }

    public function getCountries(): ?CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }

    public function getTranslations(): ?SalesChannelTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(SalesChannelTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getPaymentMethods(): ?PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(PaymentMethodCollection $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    public function getShippingMethods(): ?ShippingMethodCollection
    {
        return $this->shippingMethods;
    }

    public function setShippingMethods(ShippingMethodCollection $shippingMethods): void
    {
        $this->shippingMethods = $shippingMethods;
    }
}
