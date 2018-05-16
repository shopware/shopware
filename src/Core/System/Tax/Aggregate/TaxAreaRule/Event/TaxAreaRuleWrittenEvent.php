<?php declare(strict_types=1);

namespace Shopware\System\Tax\Aggregate\TaxAreaRule\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleDefinition;

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
