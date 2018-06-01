<?php declare(strict_types=1);

namespace Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Struct;

use Shopware\System\Language\Struct\LanguageBasicStruct;
use Shopware\System\Tax\Aggregate\TaxAreaRule\Struct\TaxAreaRuleBasicStruct;

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
