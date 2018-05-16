<?php declare(strict_types=1);

namespace Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Collection;

use Shopware\Application\Language\Collection\LanguageBasicCollection;
use Shopware\System\Tax\Aggregate\TaxAreaRule\Collection\TaxAreaRuleBasicCollection;
use Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Collection\TaxAreaRuleTranslationBasicCollection;
use Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Struct\TaxAreaRuleTranslationDetailStruct;

class TaxAreaRuleTranslationDetailCollection extends TaxAreaRuleTranslationBasicCollection
{
    /**
     * @var TaxAreaRuleTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getTaxAreaRules(): TaxAreaRuleBasicCollection
    {
        return new TaxAreaRuleBasicCollection(
            $this->fmap(function (TaxAreaRuleTranslationDetailStruct $taxAreaRuleTranslation) {
                return $taxAreaRuleTranslation->getTaxAreaRule();
            })
        );
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (TaxAreaRuleTranslationDetailStruct $taxAreaRuleTranslation) {
                return $taxAreaRuleTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return TaxAreaRuleTranslationDetailStruct::class;
    }
}
