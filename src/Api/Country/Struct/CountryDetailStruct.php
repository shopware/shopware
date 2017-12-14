<?php declare(strict_types=1);

namespace Shopware\Api\Country\Struct;

use Shopware\Api\Country\Collection\CountryStateBasicCollection;
use Shopware\Api\Country\Collection\CountryTranslationBasicCollection;
use Shopware\Api\Customer\Collection\CustomerAddressBasicCollection;
use Shopware\Api\Order\Collection\OrderAddressBasicCollection;
use Shopware\Api\Shop\Collection\ShopBasicCollection;
use Shopware\Api\Tax\Collection\TaxAreaRuleBasicCollection;

class CountryDetailStruct extends CountryBasicStruct
{
    /**
     * @var CountryAreaBasicStruct
     */
    protected $area;

    /**
     * @var CountryStateBasicCollection
     */
    protected $states;

    /**
     * @var CountryTranslationBasicCollection
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
     * @var ShopBasicCollection
     */
    protected $shops;

    /**
     * @var TaxAreaRuleBasicCollection
     */
    protected $taxAreaRules;

    public function __construct()
    {
        $this->states = new CountryStateBasicCollection();

        $this->translations = new CountryTranslationBasicCollection();

        $this->customerAddresses = new CustomerAddressBasicCollection();

        $this->orderAddresses = new OrderAddressBasicCollection();

        $this->shops = new ShopBasicCollection();

        $this->taxAreaRules = new TaxAreaRuleBasicCollection();
    }

    public function getArea(): CountryAreaBasicStruct
    {
        return $this->area;
    }

    public function setArea(CountryAreaBasicStruct $area): void
    {
        $this->area = $area;
    }

    public function getStates(): CountryStateBasicCollection
    {
        return $this->states;
    }

    public function setStates(CountryStateBasicCollection $states): void
    {
        $this->states = $states;
    }

    public function getTranslations(): CountryTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(CountryTranslationBasicCollection $translations): void
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

    public function getShops(): ShopBasicCollection
    {
        return $this->shops;
    }

    public function setShops(ShopBasicCollection $shops): void
    {
        $this->shops = $shops;
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
