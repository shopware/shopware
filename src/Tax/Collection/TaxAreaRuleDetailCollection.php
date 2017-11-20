<?php declare(strict_types=1);

namespace Shopware\Tax\Collection;

use Shopware\Country\Collection\CountryAreaBasicCollection;
use Shopware\Country\Collection\CountryBasicCollection;
use Shopware\Country\Collection\CountryStateBasicCollection;
use Shopware\Customer\Collection\CustomerGroupBasicCollection;
use Shopware\Tax\Struct\TaxAreaRuleDetailStruct;

class TaxAreaRuleDetailCollection extends TaxAreaRuleBasicCollection
{
    /**
     * @var TaxAreaRuleDetailStruct[]
     */
    protected $elements = [];

    public function getCountryAreas(): CountryAreaBasicCollection
    {
        return new CountryAreaBasicCollection(
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

    public function getCountryStates(): CountryStateBasicCollection
    {
        return new CountryStateBasicCollection(
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

    public function getTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
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
