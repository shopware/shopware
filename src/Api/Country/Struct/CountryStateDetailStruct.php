<?php declare(strict_types=1);

namespace Shopware\Api\Country\Struct;

use Shopware\Api\Country\Collection\CountryStateTranslationBasicCollection;
use Shopware\Api\Customer\Collection\CustomerAddressBasicCollection;
use Shopware\Api\Order\Collection\OrderAddressBasicCollection;
use Shopware\Api\Tax\Collection\TaxAreaRuleBasicCollection;

class CountryStateDetailStruct extends CountryStateBasicStruct
{
    /**
     * @var CountryBasicStruct
     */
    protected $country;

    /**
     * @var CountryStateTranslationBasicCollection
     */
    protected $translations;

    /**
     * @var CustomerAddressBasicCollection
     */
    protected $customerAddresses;

    /**
     * @var OrderAddressBasicCollection
     */
    protected $orderAddresses;

    /**
     * @var TaxAreaRuleBasicCollection
     */
    protected $taxAreaRules;

    public function __construct()
    {
        $this->translations = new CountryStateTranslationBasicCollection();

        $this->customerAddresses = new CustomerAddressBasicCollection();

        $this->orderAddresses = new OrderAddressBasicCollection();

        $this->taxAreaRules = new TaxAreaRuleBasicCollection();
    }

    public function getCountry(): CountryBasicStruct
    {
        return $this->country;
    }

    public function setCountry(CountryBasicStruct $country): void
    {
        $this->country = $country;
    }

    public function getTranslations(): CountryStateTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(CountryStateTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getCustomerAddresses(): CustomerAddressBasicCollection
    {
        return $this->customerAddresses;
    }

    public function setCustomerAddresses(CustomerAddressBasicCollection $customerAddresses): void
    {
        $this->customerAddresses = $customerAddresses;
    }

    public function getOrderAddresses(): OrderAddressBasicCollection
    {
        return $this->orderAddresses;
    }

    public function setOrderAddresses(OrderAddressBasicCollection $orderAddresses): void
    {
        $this->orderAddresses = $orderAddresses;
    }

    public function getTaxAreaRules(): TaxAreaRuleBasicCollection
    {
        return $this->taxAreaRules;
    }

    public function setTaxAreaRules(TaxAreaRuleBasicCollection $taxAreaRules): void
    {
        $this->taxAreaRules = $taxAreaRules;
    }
}
