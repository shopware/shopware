<?php declare(strict_types=1);

namespace Shopware\Api\Country\Struct;

use Shopware\Api\Shop\Struct\ShopBasicStruct;

class CountryAreaTranslationDetailStruct extends CountryAreaTranslationBasicStruct
{
    /**
     * @var CountryAreaBasicStruct
     */
    protected $countryArea;

    /**
     * @var ShopBasicStruct
     */
    protected $language;

    public function getCountryArea(): CountryAreaBasicStruct
    {
        return $this->countryArea;
    }

    public function setCountryArea(CountryAreaBasicStruct $countryArea): void
    {
        $this->countryArea = $countryArea;
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
