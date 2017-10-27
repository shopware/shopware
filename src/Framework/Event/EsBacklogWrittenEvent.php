<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class EsBacklogWrittenEvent extends WrittenEvent
{
    const NAME = 's_es_backlog.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_es_backlog';
    }
}
