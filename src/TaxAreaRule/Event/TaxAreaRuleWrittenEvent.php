<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class TaxAreaRuleWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'tax_area_rule.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'tax_area_rule';
    }
}
