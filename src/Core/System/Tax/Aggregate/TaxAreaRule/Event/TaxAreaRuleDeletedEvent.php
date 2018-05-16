<?php declare(strict_types=1);

namespace Shopware\System\Tax\Aggregate\TaxAreaRule\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleDefinition;

class TaxAreaRuleDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'tax_area_rule.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return TaxAreaRuleDefinition::class;
    }
}
