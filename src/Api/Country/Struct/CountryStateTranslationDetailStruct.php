<?php declare(strict_types=1);

namespace Shopware\Api\Country\Struct;

use Shopware\Api\Shop\Struct\ShopBasicStruct;

class CountryStateTranslationDetailStruct extends CountryStateTranslationBasicStruct
{
    /**
     * @var CountryStateBasicStruct
     */
    protected $countryState;

    /**
     * @var ShopBasicStruct
     */
    protected $language;

    public function getCountryState(): CountryStateBasicStruct
    {
        return $this->countryState;
    }

    public function setCountryState(CountryStateBasicStruct $countryState): void
    {
        $this->countryState = $countryState;
    }

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
