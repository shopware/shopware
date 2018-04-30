<?php declare(strict_types=1);

namespace Shopware\Api\Country\Collection;

use Shopware\Api\Country\Struct\CountryAreaTranslationDetailStruct;
use Shopware\Api\Language\Collection\LanguageBasicCollection;

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

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
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
