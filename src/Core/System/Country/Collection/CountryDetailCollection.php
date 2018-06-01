<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Collection;

use Shopware\Core\System\Country\Aggregate\CountryArea\Collection\CountryAreaBasicCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\Collection\CountryStateBasicCollection;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\Collection\CountryTranslationBasicCollection;
use Shopware\Core\System\Country\Struct\CountryDetailStruct;

class CountryDetailCollection extends CountryBasicCollection
{
    /**
     * @var CountryDetailStruct[]
     */
    protected $elements = [];

    public function getAreas(): CountryAreaBasicCollection
    {
        return new CountryAreaBasicCollection(
            $this->fmap(function (CountryDetailStruct $country) {
                return $country->getArea();
            })
        );
    }

    public function getStateIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getStates()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getStates(): CountryStateBasicCollection
    {
        $collection = new CountryStateBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getStates()->getElements());
        }

        return $collection;
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

    public function getTranslations(): CountryTranslationBasicCollection
    {
        $collection = new CountryTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function sortCountryAndStates(): void
    {
        $this->sortByPositionAndName();
        foreach ($this->elements as $country) {
            $country->getStates()->sortByPositionAndName();
        }
    }

    protected function getExpectedClass(): string
    {
        return CountryDetailStruct::class;
    }
}
