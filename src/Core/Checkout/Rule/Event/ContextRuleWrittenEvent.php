<?php declare(strict_types=1);

namespace Shopware\Checkout\Rule\Event;

use Shopware\Checkout\Rule\ContextRuleDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
