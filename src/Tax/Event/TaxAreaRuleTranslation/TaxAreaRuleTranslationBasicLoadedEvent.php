<?php declare(strict_types=1);

namespace Shopware\Tax\Event\TaxAreaRuleTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Tax\Collection\TaxAreaRuleTranslationBasicCollection;

class TaxAreaRuleTranslationBasicLoadedEvent extends NestedEvent
{
    const NAME = 'tax_area_rule_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var TaxAreaRuleTranslationBasicCollection
     */
    protected $taxAreaRuleTranslations;

    public function __construct(TaxAreaRuleTranslationBasicCollection $taxAreaRuleTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->taxAreaRuleTranslations = $taxAreaRuleTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getTaxAreaRuleTranslations(): TaxAreaRuleTranslationBasicCollection
    {
        return $this->taxAreaRuleTranslations;
    }
}
