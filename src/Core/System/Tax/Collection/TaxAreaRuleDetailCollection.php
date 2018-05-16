<?php declare(strict_types=1);

namespace Shopware\System\Tax\Collection;

use Shopware\System\Country\Aggregate\CountryArea\Collection\CountryAreaBasicCollection;
use Shopware\System\Country\Collection\CountryBasicCollection;
use Shopware\System\Country\Aggregate\CountryState\Collection\CountryStateBasicCollection;
use Shopware\Checkout\Customer\Collection\CustomerGroupBasicCollection;
use Shopware\System\Tax\Struct\TaxAreaRuleDetailStruct;

class TaxAreaRuleDetailCollection extends TaxAreaRuleBasicCollection
{
    /**
     * @var TaxAreaRuleDetailStruct[]
     */
    protected $elements = [];

    public function getCountryAreas(): CountryAreaBasicCollection
    {
        return new \Shopware\System\Country\Aggregate\CountryArea\Collection\CountryAreaBasicCollection(
            $this->fmap(function (TaxAreaRuleDetailStruct $taxAreaRule) {
                return $taxAreaRule->getCountryArea();
            })
        );
    }

    public function getCountries(): CountryBasicCollection
    {
        return new CountryBasicCollection(
            $this->fmap(function (TaxAreaRuleDetailStruct $taxAreaRule) {
                return $taxAreaRule->getCountry();
            })
        );
    }

    public function getCountryStates(): \Shopware\System\Country\Aggregate\CountryState\Collection\CountryStateBasicCollection
    {
        return new \Shopware\System\Country\Aggregate\CountryState\Collection\CountryStateBasicCollection(
            $this->fmap(function (TaxAreaRuleDetailStruct $taxAreaRule) {
                return $taxAreaRule->getCountryState();
            })
        );
    }

    public function getTaxes(): TaxBasicCollection
    {
        return new TaxBasicCollection(
            $this->fmap(function (TaxAreaRuleDetailStruct $taxAreaRule) {
                return $taxAreaRule->getTax();
            })
        );
    }

    public function getCustomerGroups(): CustomerGroupBasicCollection
    {
        return new CustomerGroupBasicCollection(
            $this->fmap(function (TaxAreaRuleDetailStruct $taxAreaRule) {
                return $taxAreaRule->getCustomerGroup();
            })
        );
    }

    public function getTranslationIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getTranslations(): TaxAreaRuleTranslationBasicCollection
    {
        $collection = new TaxAreaRuleTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return TaxAreaRuleDetailStruct::class;
    }
}
