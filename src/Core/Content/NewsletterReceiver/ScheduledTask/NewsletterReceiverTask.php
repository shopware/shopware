<?php declare(strict_types=1);

namespace Shopware\Core\Content\NewsletterReceiver\ScheduledTask;

use Shopware\Core\Framework\ScheduledTask\ScheduledTask;

class NewsletterReceiverTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'delete_newsletter_receiver_task';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // 1 day
    }
}
