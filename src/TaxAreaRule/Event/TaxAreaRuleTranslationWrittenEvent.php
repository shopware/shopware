<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class TaxAreaRuleTranslationWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'tax_area_rule_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'tax_area_rule_translation';
    }
}
