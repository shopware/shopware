<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class SessionsWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'sessions.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'sessions';
    }
}
