<?php declare(strict_types=1);

namespace Shopware\System\Currency\Aggregate\CurrencyTranslation\Struct;

use Shopware\Application\Language\Struct\LanguageBasicStruct;
use Shopware\System\Currency\Struct\CurrencyBasicStruct;

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
