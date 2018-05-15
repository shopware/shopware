<?php declare(strict_types=1);

namespace Shopware\System\Tax\Struct;

use Shopware\System\Country\Struct\CountryAreaBasicStruct;
use Shopware\System\Country\Struct\CountryBasicStruct;
use Shopware\System\Country\Struct\CountryStateBasicStruct;
use Shopware\Checkout\Customer\Struct\CustomerGroupBasicStruct;
use Shopware\System\Tax\Collection\TaxAreaRuleTranslationBasicCollection;

class TaxAreaRuleDetailStruct extends TaxAreaRuleBasicStruct
{
    /**
     * @var CountryAreaBasicStruct|null
     */
    protected $countryArea;

    /**
     * @var CountryBasicStruct|null
     */
    protected $country;

    /**
     * @var CountryStateBasicStruct|null
     */
    protected $countryState;

    /**
     * @var TaxBasicStruct
     */
    protected $tax;

    /**
     * @var CustomerGroupBasicStruct
     */
    protected $customerGroup;

    /**
     * @var TaxAreaRuleTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new TaxAreaRuleTranslationBasicCollection();
    }

    public function getCountryArea(): ?CountryAreaBasicStruct
    {
        return $this->countryArea;
    }

    public function setCountryArea(?CountryAreaBasicStruct $countryArea): void
    {
        $this->countryArea = $countryArea;
    }

    public function getCountry(): ?CountryBasicStruct
    {
        return $this->country;
    }

    public function setCountry(?CountryBasicStruct $country): void
    {
        $this->country = $country;
    }

    public function getCountryState(): ?CountryStateBasicStruct
    {
        return $this->countryState;
    }

    public function setCountryState(?CountryStateBasicStruct $countryState): void
    {
        $this->countryState = $countryState;
    }

    public function getTax(): TaxBasicStruct
    {
        return $this->tax;
    }

    public function setTax(TaxBasicStruct $tax): void
    {
        $this->tax = $tax;
    }

    public function getCustomerGroup(): CustomerGroupBasicStruct
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroupBasicStruct $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }

    public function getTranslations(): TaxAreaRuleTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(TaxAreaRuleTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
