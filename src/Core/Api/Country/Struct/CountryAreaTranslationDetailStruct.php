<?php declare(strict_types=1);

namespace Shopware\Api\Country\Struct;

use Shopware\Api\Language\Struct\LanguageBasicStruct;

class CountryAreaTranslationDetailStruct extends CountryAreaTranslationBasicStruct
{
    /**
     * @var CountryAreaBasicStruct
     */
    protected $countryArea;

    /**
     * @var LanguageBasicStruct
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

    public function getLanguage(): LanguageBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageBasicStruct $language): void
    {
        $this->language = $language;
    }
}
