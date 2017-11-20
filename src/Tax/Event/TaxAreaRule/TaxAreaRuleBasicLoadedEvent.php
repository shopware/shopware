<?php declare(strict_types=1);

namespace Shopware\Tax\Event\TaxAreaRule;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Tax\Collection\TaxAreaRuleBasicCollection;

class TaxAreaRuleBasicLoadedEvent extends NestedEvent
{
    const NAME = 'tax_area_rule.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var TaxAreaRuleBasicCollection
     */
    protected $taxAreaRules;

    public function __construct(TaxAreaRuleBasicCollection $taxAreaRules, TranslationContext $context)
    {
        $this->context = $context;
        $this->taxAreaRules = $taxAreaRules;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getTaxAreaRules(): TaxAreaRuleBasicCollection
    {
        return $this->taxAreaRules;
    }
}
