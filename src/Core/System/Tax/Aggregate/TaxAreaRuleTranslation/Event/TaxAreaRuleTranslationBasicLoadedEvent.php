<?php declare(strict_types=1);

namespace Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\Collection\TaxAreaRuleTranslationBasicCollection;

class TaxAreaRuleTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'tax_area_rule_translation.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var TaxAreaRuleTranslationBasicCollection
     */
    protected $taxAreaRuleTranslations;

    public function __construct(TaxAreaRuleTranslationBasicCollection $taxAreaRuleTranslations, Context $context)
    {
        $this->context = $context;
        $this->taxAreaRuleTranslations = $taxAreaRuleTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getTaxAreaRuleTranslations(): TaxAreaRuleTranslationBasicCollection
    {
        return $this->taxAreaRuleTranslations;
    }
}
