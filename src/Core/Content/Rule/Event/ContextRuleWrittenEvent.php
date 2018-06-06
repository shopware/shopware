<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Event;

use Shopware\Core\Content\Rule\ContextRuleDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

class ContextRuleWrittenEvent extends WrittenEvent
{
    public const NAME = 'context_rule.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ContextRuleDefinition::class;
    }
}
