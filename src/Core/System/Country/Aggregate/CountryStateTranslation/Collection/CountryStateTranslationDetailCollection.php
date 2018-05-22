<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryStateTranslation\Collection;

use Shopware\Application\Language\Collection\LanguageBasicCollection;
use Shopware\System\Country\Aggregate\CountryState\Collection\CountryStateBasicCollection;
use Shopware\System\Country\Aggregate\CountryStateTranslation\Struct\CountryStateTranslationDetailStruct;

class CountryStateTranslationDetailCollection extends CountryStateTranslationBasicCollection
{
    /**
     * @var CountryStateTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getCountryStates(): CountryStateBasicCollection
    {
        return new CountryStateBasicCollection(
            $this->fmap(function (CountryStateTranslationDetailStruct $countryStateTranslation) {
                return $countryStateTranslation->getCountryState();
            })
        );
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (CountryStateTranslationDetailStruct $countryStateTranslation) {
                return $countryStateTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return CountryStateTranslationDetailStruct::class;
    }
}
