<?php declare(strict_types=1);

namespace Shopware\Locale\Struct;

use Shopware\Shop\Struct\ShopBasicStruct;

class LocaleTranslationDetailStruct extends LocaleTranslationBasicStruct
{
    /**
     * @var LocaleBasicStruct
     */
    protected $locale;

    /**
     * @var ShopBasicStruct
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

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
