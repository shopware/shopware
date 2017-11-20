<?php declare(strict_types=1);

namespace Shopware\Country\Collection;

use Shopware\Country\Struct\CountryAreaDetailStruct;
use Shopware\Tax\Collection\TaxAreaRuleBasicCollection;

class CountryAreaDetailCollection extends CountryAreaBasicCollection
{
    /**
     * @var CountryAreaDetailStruct[]
     */
    protected $elements = [];

    public function getCountryUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCountries()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCountries(): CountryBasicCollection
    {
        $collection = new CountryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCountries()->getElements());
        }

        return $collection;
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

    public function getTranslations(): CountryAreaTranslationBasicCollection
    {
        $collection = new CountryAreaTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getTaxAreaRuleUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTaxAreaRules()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getTaxAreaRules(): TaxAreaRuleBasicCollection
    {
        $collection = new TaxAreaRuleBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTaxAreaRules()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return CountryAreaDetailStruct::class;
    }
}
