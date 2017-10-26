<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class MultiEditQueueWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 's_multi_edit_queue.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_multi_edit_queue';
    }
}
