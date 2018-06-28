<?php declare(strict_types=1);

namespace Shopware\Core\System\Touchpoint;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodStruct;
use Shopware\Core\Checkout\Shipping\ShippingMethodStruct;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Country\CountryStruct;
use Shopware\Core\System\Currency\CurrencyStruct;
use Shopware\Core\System\Language\LanguageStruct;

class TouchpointStruct extends Entity
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
}
