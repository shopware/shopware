<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class MultiEditQueueArticlesWrittenEvent extends EntityWrittenEvent
{
    const NAME = 's_multi_edit_queue_articles.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_multi_edit_queue_articles';
    }
}
