<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Event;

use Shopware\Core\Content\Rule\ContextRuleDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
