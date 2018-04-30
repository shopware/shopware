<?php declare(strict_types=1);

namespace Shopware\Api\Context\Event\ContextRule;

use Shopware\Api\Context\Definition\ContextRuleDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

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
