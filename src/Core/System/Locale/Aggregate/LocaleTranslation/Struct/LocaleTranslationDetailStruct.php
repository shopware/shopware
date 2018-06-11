<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Aggregate\LocaleTranslation\Struct;

use Shopware\Core\System\Language\Struct\LanguageBasicStruct;
use Shopware\Core\System\Locale\Struct\LocaleBasicStruct;

class LocaleTranslationDetailStruct extends LocaleTranslationBasicStruct
{
    /**
     * @var LocaleBasicStruct
     */
    protected $locale;

    /**
     * @var LanguageBasicStruct
     */
    protected $language;

    public function getLocale(): LocaleBasicStruct
    {
        return $this->locale;
    }

    public function setLocale(LocaleBasicStruct $locale): void
    {
        $this->locale = $locale;
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
