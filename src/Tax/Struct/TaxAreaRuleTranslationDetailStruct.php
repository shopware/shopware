<?php declare(strict_types=1);

namespace Shopware\Tax\Struct;

use Shopware\Shop\Struct\ShopBasicStruct;

class TaxAreaRuleTranslationDetailStruct extends TaxAreaRuleTranslationBasicStruct
{
    /**
     * @var TaxAreaRuleBasicStruct
     */
    protected $taxAreaRule;

    /**
     * @var ShopBasicStruct
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

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
