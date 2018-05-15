<?php declare(strict_types=1);

namespace Shopware\System\Currency\Struct;

use Shopware\Api\Language\Struct\LanguageBasicStruct;

class CurrencyTranslationDetailStruct extends CurrencyTranslationBasicStruct
{
    /**
     * @var CurrencyBasicStruct
     */
    protected $currency;

    /**
     * @var LanguageBasicStruct
     */
    protected $language;

    public function getCurrency(): CurrencyBasicStruct
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyBasicStruct $currency): void
    {
        $this->currency = $currency;
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
