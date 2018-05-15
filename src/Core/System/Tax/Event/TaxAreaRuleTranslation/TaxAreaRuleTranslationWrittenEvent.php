<?php declare(strict_types=1);

namespace Shopware\System\Tax\Event\TaxAreaRuleTranslation;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Tax\Definition\TaxAreaRuleTranslationDefinition;

class TaxAreaRuleTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'tax_area_rule_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return TaxAreaRuleTranslationDefinition::class;
    }
}
