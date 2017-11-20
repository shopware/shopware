<?php declare(strict_types=1);

namespace Shopware\Tax\Event\TaxAreaRule;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Tax\Definition\TaxAreaRuleDefinition;

class TaxAreaRuleWrittenEvent extends WrittenEvent
{
    const NAME = 'tax_area_rule.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return TaxAreaRuleDefinition::class;
    }
}
