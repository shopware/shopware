<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Struct;

use Shopware\Core\System\Currency\Struct\CurrencyBasicStruct;
use Shopware\Core\System\Language\Struct\LanguageBasicStruct;

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
