<?php declare(strict_types=1);

namespace Shopware\System\Tax\Aggregate\TaxAreaRule\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Tax\Aggregate\TaxAreaRule\Collection\TaxAreaRuleBasicCollection;

class TaxAreaRuleBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'tax_area_rule.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\System\Tax\Aggregate\TaxAreaRule\Collection\TaxAreaRuleBasicCollection
     */
    protected $taxAreaRules;

    public function __construct(TaxAreaRuleBasicCollection $taxAreaRules, Context $context)
    {
        $this->context = $context;
        $this->taxAreaRules = $taxAreaRules;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getTaxAreaRules(): TaxAreaRuleBasicCollection
    {
        return $this->taxAreaRules;
    }
}
