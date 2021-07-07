<?php declare(strict_types=1);

namespace Shopware\Core\System\Country;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationCollection;
use Shopware\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleCollection;

class CountryEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $iso;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var bool
     *
     * @feature-deprecated (flag:FEATURE_NEXT_14114) tag:v6.5.0 - Will be removed, use $customerTax->getEnabled() instead
     */
    protected $taxFree;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var bool
     */
    protected $shippingAvailable;

    /**
     * @var string|null
     */
    protected $iso3;

    /**
     * @var bool
     */
    protected $displayStateInRegistration;

    /**
     * @var bool
     */
    protected $forceStateInRegistration;

    /**
     * @var bool
     *
     * @feature-deprecated (flag:FEATURE_NEXT_14114) tag:v6.5.0 - Will be removed, use $companyTax->getEnabled() instead
     */
    protected $companyTaxFree;

    /**
     * @var bool
     */
    protected $checkVatIdPattern;

    /**
     * @var string|null
     */
    protected $vatIdPattern;

    /**
     * @internal (flag:FEATURE_NEXT_14114)
     *
     * @var bool|null
     */
    protected $vatIdRequired;

    /**
     * @internal (flag:FEATURE_NEXT_14114)
     */
    protected TaxFreeConfig $customerTax;

    /**
     * @internal (flag:FEATURE_NEXT_14114)
     */
    protected TaxFreeConfig $companyTax;

    /**
     * @var CountryStateCollection|null
     */
    protected $states;

    /**
     * @var CountryTranslationCollection|null
     */
    protected $translations;

    /**
     * @var OrderAddressCollection|null
     */
    protected $orderAddresses;

    /**
     * @var CustomerAddressCollection|null
     */
    protected $customerAddresses;

    /**
     * @var SalesChannelCollection|null
     */
    protected $salesChannelDefaultAssignments;

    /**
     * @var SalesChannelCollection|null
     */
    protected $salesChannels;

    /**
     * @var TaxRuleCollection|null
     */
    protected $taxRules;

    /**
     * @var CurrencyCountryRoundingCollection|null
     */
    protected $currencyCountryRoundings;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getIso(): ?string
    {
        return $this->iso;
    }

    public function setIso(?string $iso): void
    {
        $this->iso = $iso;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_14114) - Will be removed in version 6.5.0
     */
    public function getTaxFree(): bool
    {
        Feature::triggerDeprecated('FEATURE_NEXT_14114', '6.4.0', '6.5.0', 'Will be removed in version 6.5.0, use $customerTax->getEnabled() instead.');

        return $this->taxFree;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_14114) - Will be removed in version 6.5.0
     */
    public function setTaxFree(bool $taxFree): void
    {
        Feature::triggerDeprecated('FEATURE_NEXT_14114', '6.4.0', '6.5.0', 'Will be removed in version 6.5.0.');

        $this->taxFree = $taxFree;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getShippingAvailable(): bool
    {
        return $this->shippingAvailable;
    }

    public function setShippingAvailable(bool $shippingAvailable): void
    {
        $this->shippingAvailable = $shippingAvailable;
    }

    public function getIso3(): ?string
    {
        return $this->iso3;
    }

    public function setIso3(?string $iso3): void
    {
        $this->iso3 = $iso3;
    }

    public function getDisplayStateInRegistration(): bool
    {
        return $this->displayStateInRegistration;
    }

    public function setDisplayStateInRegistration(bool $displayStateInRegistration): void
    {
        $this->displayStateInRegistration = $displayStateInRegistration;
    }

    public function getForceStateInRegistration(): bool
    {
        return $this->forceStateInRegistration;
    }

    public function setForceStateInRegistration(bool $forceStateInRegistration): void
    {
        $this->forceStateInRegistration = $forceStateInRegistration;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_14114) - Will be removed in version 6.5.0
     */
    public function getCompanyTaxFree(): bool
    {
        Feature::triggerDeprecated('FEATURE_NEXT_14114', '6.4.0', '6.5.0', 'Will be removed in version 6.5.0, use $companyTax->getEnabled() instead.');

        return $this->companyTaxFree;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_14114) - Will be removed in version 6.5.0
     */
    public function setCompanyTaxFree(bool $companyTaxFree): void
    {
        Feature::triggerDeprecated('FEATURE_NEXT_14114', '6.4.0', '6.5.0', 'Will be removed in version 6.5.0.');

        $this->companyTaxFree = $companyTaxFree;
    }

    public function getCheckVatIdPattern(): bool
    {
        return $this->checkVatIdPattern;
    }

    public function setCheckVatIdPattern(bool $checkVatIdPattern): void
    {
        $this->checkVatIdPattern = $checkVatIdPattern;
    }

    public function getVatIdPattern(): ?string
    {
        return $this->vatIdPattern;
    }

    public function setVatIdPattern(?string $vatIdPattern): void
    {
        $this->vatIdPattern = $vatIdPattern;
    }

    public function getStates(): ?CountryStateCollection
    {
        return $this->states;
    }

    public function setStates(CountryStateCollection $states): void
    {
        $this->states = $states;
    }

    public function getTranslations(): ?CountryTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(CountryTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getOrderAddresses(): ?OrderAddressCollection
    {
        return $this->orderAddresses;
    }

    public function setOrderAddresses(OrderAddressCollection $orderAddresses): void
    {
        $this->orderAddresses = $orderAddresses;
    }

    public function getCustomerAddresses(): ?CustomerAddressCollection
    {
        return $this->customerAddresses;
    }

    public function setCustomerAddresses(CustomerAddressCollection $customerAddresses): void
    {
        $this->customerAddresses = $customerAddresses;
    }

    public function getSalesChannelDefaultAssignments(): ?SalesChannelCollection
    {
        return $this->salesChannelDefaultAssignments;
    }

    public function setSalesChannelDefaultAssignments(SalesChannelCollection $salesChannelDefaultAssignments): void
    {
        $this->salesChannelDefaultAssignments = $salesChannelDefaultAssignments;
    }

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }

    public function getTaxRules(): ?TaxRuleCollection
    {
        return $this->taxRules;
    }

    public function setTaxRules(TaxRuleCollection $taxRules): void
    {
        $this->taxRules = $taxRules;
    }

    public function getCurrencyCountryRoundings(): ?CurrencyCountryRoundingCollection
    {
        return $this->currencyCountryRoundings;
    }

    public function setCurrencyCountryRoundings(CurrencyCountryRoundingCollection $currencyCountryRoundings): void
    {
        $this->currencyCountryRoundings = $currencyCountryRoundings;
    }

    /**
     * @internal (flag:FEATURE_NEXT_14114)
     */
    public function getVatIdRequired(): bool
    {
        return (bool) $this->vatIdRequired;
    }

    /**
     * @internal (flag:FEATURE_NEXT_14114)
     */
    public function setVatIdRequired(bool $vatIdRequired): void
    {
        $this->vatIdRequired = $vatIdRequired;
    }

    /**
     * @internal (flag:FEATURE_NEXT_14114)
     */
    public function getCustomerTax(): TaxFreeConfig
    {
        return $this->customerTax;
    }

    /**
     * @internal (flag:FEATURE_NEXT_14114)
     */
    public function setCustomerTax(TaxFreeConfig $customerTax): void
    {
        $this->customerTax = $customerTax;
    }

    /**
     * @internal (flag:FEATURE_NEXT_14114)
     */
    public function getCompanyTax(): TaxFreeConfig
    {
        return $this->companyTax;
    }

    /**
     * @internal (flag:FEATURE_NEXT_14114)
     */
    public function setCompanyTax(TaxFreeConfig $companyTax): void
    {
        $this->companyTax = $companyTax;
    }
}
