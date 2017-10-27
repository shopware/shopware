<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Event;

use Shopware\Api\Write\WrittenEvent;

class TaxAreaRuleWrittenEvent extends WrittenEvent
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
