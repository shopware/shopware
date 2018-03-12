<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Struct;

use Shopware\Api\Language\Struct\LanguageBasicStruct;

class TaxAreaRuleTranslationDetailStruct extends TaxAreaRuleTranslationBasicStruct
{
    /**
     * @var TaxAreaRuleBasicStruct
     */
    protected $taxAreaRule;

    /**
     * @var LanguageBasicStruct
     */
    protected $language;

    public function getTaxAreaRule(): TaxAreaRuleBasicStruct
    {
        return $this->taxAreaRule;
    }

    public function setTaxAreaRule(TaxAreaRuleBasicStruct $taxAreaRule): void
    {
        $this->taxAreaRule = $taxAreaRule;
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
