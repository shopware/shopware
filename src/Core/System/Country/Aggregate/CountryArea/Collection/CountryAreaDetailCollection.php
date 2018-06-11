<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryArea\Collection;

use Shopware\Core\System\Country\Aggregate\CountryArea\Struct\CountryAreaDetailStruct;
use Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\Collection\CountryAreaTranslationBasicCollection;
use Shopware\Core\System\Country\Collection\CountryBasicCollection;

class CountryAreaDetailCollection extends CountryAreaBasicCollection
{
    /**
     * @var \Shopware\Core\System\Country\Aggregate\CountryArea\Struct\CountryAreaDetailStruct[]
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
