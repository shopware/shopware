<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('customer-order')]
class NewsletterRecipientTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'delete_newsletter_recipient_task';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // 1 day
    }
}
