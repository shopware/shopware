<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
#[Package('framework')]
class DrainWebhookOutboxTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'webhook_outbox.drain';
    }

    public static function getDefaultInterval(): int
    {
        return self::MINUTELY * 5;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
