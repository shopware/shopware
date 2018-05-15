<?php declare(strict_types=1);

namespace Shopware\System\Country\Collection;

use Shopware\System\Country\Struct\CountryTranslationDetailStruct;
use Shopware\Application\Language\Collection\LanguageBasicCollection;

class CountryTranslationDetailCollection extends CountryTranslationBasicCollection
{
    /**
     * @var CountryTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getCountries(): CountryBasicCollection
    {
        return new CountryBasicCollection(
            $this->fmap(function (CountryTranslationDetailStruct $countryTranslation) {
                return $countryTranslation->getCountry();
            })
        );
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (CountryTranslationDetailStruct $countryTranslation) {
                return $countryTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return CountryTranslationDetailStruct::class;
    }
}
