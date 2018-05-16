<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryAreaTranslation\Collection;

use Shopware\System\Country\Aggregate\CountryArea\Collection\CountryAreaBasicCollection;

use Shopware\System\Country\Aggregate\CountryAreaTranslation\Struct\CountryAreaTranslationDetailStruct;
use Shopware\Application\Language\Collection\LanguageBasicCollection;

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
