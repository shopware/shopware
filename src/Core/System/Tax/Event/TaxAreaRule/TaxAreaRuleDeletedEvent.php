<?php declare(strict_types=1);

namespace Shopware\System\Tax\Event\TaxAreaRule;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Tax\Definition\TaxAreaRuleDefinition;

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
