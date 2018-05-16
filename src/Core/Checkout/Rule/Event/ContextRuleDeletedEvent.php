<?php declare(strict_types=1);

namespace Shopware\Checkout\Rule\Event;

use Shopware\Checkout\Rule\ContextRuleDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
