<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask;

class RequeueDeadMessagesTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'requeue_dead_messages';
    }

    public static function getDefaultInterval(): int
    {
        return 300; //every 5 min
    }
}
