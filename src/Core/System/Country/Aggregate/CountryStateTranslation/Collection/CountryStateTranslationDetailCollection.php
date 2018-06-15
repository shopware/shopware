<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryStateTranslation\Collection;

use Shopware\Core\System\Country\Aggregate\CountryState\Collection\CountryStateBasicCollection;
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\Struct\CountryStateTranslationDetailStruct;
use Shopware\Core\System\Language\Collection\LanguageBasicCollection;

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
