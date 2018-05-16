<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryArea\Collection;


use Shopware\System\Country\Aggregate\CountryAreaTranslation\Collection\CountryAreaTranslationBasicCollection;
use Shopware\System\Country\Collection\CountryBasicCollection;
use Shopware\System\Country\Aggregate\CountryArea\Struct\CountryAreaDetailStruct;

class CountryAreaDetailCollection extends CountryAreaBasicCollection
{
    /**
     * @var \Shopware\System\Country\Aggregate\CountryArea\Struct\CountryAreaDetailStruct[]
     */
    protected $elements = [];

    public function getCountryIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCountries()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getCountries(): CountryBasicCollection
    {
        $collection = new CountryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCountries()->getElements());
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

    public function getTranslations(): CountryAreaTranslationBasicCollection
    {
        $collection = new CountryAreaTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return CountryAreaDetailStruct::class;
    }
}
