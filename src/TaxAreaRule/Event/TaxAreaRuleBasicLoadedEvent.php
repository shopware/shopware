<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\TaxAreaRule\Struct\TaxAreaRuleBasicCollection;

class TaxAreaRuleBasicLoadedEvent extends NestedEvent
{
    const NAME = 'taxAreaRule.basic.loaded';

    /**
     * @var TaxAreaRuleBasicCollection
     */
    protected $taxAreaRules;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(TaxAreaRuleBasicCollection $taxAreaRules, TranslationContext $context)
    {
        $this->taxAreaRules = $taxAreaRules;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getTaxAreaRules(): TaxAreaRuleBasicCollection
    {
        return $this->taxAreaRules;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
        ]);
    }
}
