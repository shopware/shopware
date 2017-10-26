<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class CoreRulesetsWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 's_core_rulesets.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_core_rulesets';
    }
}
