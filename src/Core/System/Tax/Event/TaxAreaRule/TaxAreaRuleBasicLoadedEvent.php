<?php declare(strict_types=1);

namespace Shopware\System\Tax\Event\TaxAreaRule;

use Shopware\System\Tax\Collection\TaxAreaRuleBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class TaxAreaRuleBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'tax_area_rule.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var TaxAreaRuleBasicCollection
     */
    protected $taxAreaRules;

    public function __construct(TaxAreaRuleBasicCollection $taxAreaRules, ApplicationContext $context)
    {
        $this->context = $context;
        $this->taxAreaRules = $taxAreaRules;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getTaxAreaRules(): TaxAreaRuleBasicCollection
    {
        return $this->taxAreaRules;
    }
}
