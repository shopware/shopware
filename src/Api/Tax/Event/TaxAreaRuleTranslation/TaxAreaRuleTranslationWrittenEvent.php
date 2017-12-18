<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Event\TaxAreaRuleTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Tax\Definition\TaxAreaRuleTranslationDefinition;

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
