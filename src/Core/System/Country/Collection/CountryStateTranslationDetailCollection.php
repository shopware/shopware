<?php declare(strict_types=1);

namespace Shopware\System\Country\Collection;

use Shopware\System\Country\Struct\CountryStateTranslationDetailStruct;
use Shopware\Application\Language\Collection\LanguageBasicCollection;

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
