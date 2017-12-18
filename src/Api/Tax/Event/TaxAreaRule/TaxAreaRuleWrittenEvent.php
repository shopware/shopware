<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Event\TaxAreaRule;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Tax\Definition\TaxAreaRuleDefinition;

class TaxAreaRuleWrittenEvent extends WrittenEvent
{
    public const NAME = 'tax_area_rule.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return TaxAreaRuleDefinition::class;
    }
}
