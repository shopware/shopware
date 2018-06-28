<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxAreaRule;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupStruct;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Country\Aggregate\CountryArea\CountryAreaStruct;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateStruct;
use Shopware\Core\System\Country\CountryStruct;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation\TaxAreaRuleTranslationCollection;
use Shopware\Core\System\Tax\TaxStruct;

class TaxAreaRuleStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $countryAreaId;

    /**
     * @var string|null
     */
    protected $countryId;

    /**
     * @var string|null
     */
    protected $countryStateId;

    /**
     * @var string
     */
    protected $taxId;

    /**
     * @var string
     */
    protected $customerGroupId;

    /**
     * @var float
     */
    protected $taxRate;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var CountryAreaStruct|null
     */
    protected $countryArea;

    /**
     * @var CountryStruct|null
     */
    protected $country;

    /**
     * @var CountryStateStruct|null
     */
    protected $countryState;

    /**
     * @var TaxStruct|null
     */
    protected $tax;

    /**
     * @var CustomerGroupStruct|null
     */
    protected $customerGroup;

    /**
     * @var TaxAreaRuleTranslationCollection|null
     */
    protected $translations;

    public function getCountryAreaId(): ?string
    {
        return $this->countryAreaId;
    }

    public function setCountryAreaId(?string $countryAreaId): void
    {
        $this->countryAreaId = $countryAreaId;
    }

    public function getCountryId(): ?string
    {
        return $this->countryId;
    }

    public function setCountryId(?string $countryId): void
    {
        $this->countryId = $countryId;
    }

    public function getCountryStateId(): ?string
    {
        return $this->countryStateId;
    }

    public function setCountryStateId(?string $countryStateId): void
    {
        $this->countryStateId = $countryStateId;
    }

    public function getTaxId(): string
    {
        return $this->taxId;
    }

    public function setTaxId(string $taxId): void
    {
        $this->taxId = $taxId;
    }

    public function getCustomerGroupId(): string
    {
        return $this->customerGroupId;
    }

    public function setCustomerGroupId(string $customerGroupId): void
    {
        $this->customerGroupId = $customerGroupId;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function setTaxRate(float $taxRate): void
    {
        $this->taxRate = $taxRate;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
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

    public function getCountryArea(): ?CountryAreaStruct
    {
        return $this->countryArea;
    }

    public function setCountryArea(CountryAreaStruct $countryArea): void
    {
        $this->countryArea = $countryArea;
    }

    public function getCountry(): ?CountryStruct
    {
        return $this->country;
    }

    public function setCountry(CountryStruct $country): void
    {
        $this->country = $country;
    }

    public function getCountryState(): ?CountryStateStruct
    {
        return $this->countryState;
    }

    public function setCountryState(CountryStateStruct $countryState): void
    {
        $this->countryState = $countryState;
    }

    public function getTax(): ?TaxStruct
    {
        return $this->tax;
    }

    public function setTax(TaxStruct $tax): void
    {
        $this->tax = $tax;
    }

    public function getCustomerGroup(): ?CustomerGroupStruct
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroupStruct $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }

    public function getTranslations(): ?TaxAreaRuleTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(TaxAreaRuleTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
