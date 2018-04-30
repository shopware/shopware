<?php declare(strict_types=1);

namespace Shopware\Api\Context\Event\ContextRule;

use Shopware\Api\Context\Definition\ContextRuleDefinition;
use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;

class ContextRuleDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'context_rule.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ContextRuleDefinition::class;
    }
}
