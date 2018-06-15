<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\Struct;

use Shopware\Core\System\Country\Aggregate\CountryArea\Struct\CountryAreaBasicStruct;
use Shopware\Core\System\Language\Struct\LanguageBasicStruct;

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
