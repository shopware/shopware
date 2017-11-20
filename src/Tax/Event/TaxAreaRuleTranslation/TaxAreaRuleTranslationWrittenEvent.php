<?php declare(strict_types=1);

namespace Shopware\Tax\Event\TaxAreaRuleTranslation;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Tax\Definition\TaxAreaRuleTranslationDefinition;

class TaxAreaRuleTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'tax_area_rule_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return TaxAreaRuleTranslationDefinition::class;
    }
}
