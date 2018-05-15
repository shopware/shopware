<?php declare(strict_types=1);

namespace Shopware\System\Country\Struct;

use Shopware\Application\Language\Struct\LanguageBasicStruct;

class CountryStateTranslationDetailStruct extends CountryStateTranslationBasicStruct
{
    /**
     * @var CountryStateBasicStruct
     */
    protected $countryState;

    /**
     * @var LanguageBasicStruct
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

    public function getLanguage(): LanguageBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageBasicStruct $language): void
    {
        $this->language = $language;
    }
}
