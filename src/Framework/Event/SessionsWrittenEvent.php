<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class SessionsWrittenEvent extends WrittenEvent
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
