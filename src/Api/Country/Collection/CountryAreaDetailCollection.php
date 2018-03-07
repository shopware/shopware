<?php declare(strict_types=1);

namespace Shopware\Api\Country\Collection;

use Shopware\Api\Country\Struct\CountryAreaDetailStruct;

class CountryAreaDetailCollection extends CountryAreaBasicCollection
{
    /**
     * @var CountryAreaDetailStruct[]
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
