<?php declare(strict_types=1);

namespace Shopware\Country\Collection;

use Shopware\Country\Struct\CountryAreaTranslationDetailStruct;
use Shopware\Shop\Collection\ShopBasicCollection;

class CountryAreaTranslationDetailCollection extends CountryAreaTranslationBasicCollection
{
    /**
     * @var CountryAreaTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getCountryAreas(): CountryAreaBasicCollection
    {
        return new CountryAreaBasicCollection(
            $this->fmap(function (CountryAreaTranslationDetailStruct $countryAreaTranslation) {
                return $countryAreaTranslation->getCountryArea();
            })
        );
    }

    public function getLanguages(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (CountryAreaTranslationDetailStruct $countryAreaTranslation) {
                return $countryAreaTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return CountryAreaTranslationDetailStruct::class;
    }
}
