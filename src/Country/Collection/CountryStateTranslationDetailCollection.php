<?php declare(strict_types=1);

namespace Shopware\Country\Collection;

use Shopware\Country\Struct\CountryStateTranslationDetailStruct;
use Shopware\Shop\Collection\ShopBasicCollection;

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

    public function getLanguages(): ShopBasicCollection
    {
        return new ShopBasicCollection(
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
