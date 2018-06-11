<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Event;

use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

class RuleWrittenEvent extends WrittenEvent
{
    public const NAME = 'rule.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return RuleDefinition::class;
    }
}
