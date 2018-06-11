<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryState\Collection;

use Shopware\Core\System\Country\Aggregate\CountryState\Struct\CountryStateDetailStruct;
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\Collection\CountryStateTranslationBasicCollection;
use Shopware\Core\System\Country\Collection\CountryBasicCollection;

class CountryStateDetailCollection extends CountryStateBasicCollection
{
    /**
     * @var \Shopware\Core\System\Country\Aggregate\CountryState\Struct\CountryStateDetailStruct[]
     */
    protected $elements = [];

    public function getCountries(): CountryBasicCollection
    {
        return new CountryBasicCollection(
            $this->fmap(function (CountryStateDetailStruct $countryState) {
                return $countryState->getCountry();
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

    public function getTranslations(): CountryStateTranslationBasicCollection
    {
        $collection = new \Shopware\Core\System\Country\Aggregate\CountryStateTranslation\Collection\CountryStateTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return CountryStateDetailStruct::class;
    }
}
