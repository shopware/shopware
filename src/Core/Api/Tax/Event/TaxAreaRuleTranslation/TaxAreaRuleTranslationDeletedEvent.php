<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Event\TaxAreaRuleTranslation;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Tax\Definition\TaxAreaRuleTranslationDefinition;

class TaxAreaRuleTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'tax_area_rule_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return TaxAreaRuleTranslationDefinition::class;
    }
}
