<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @package core
 */
class LogCleanupTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'log_entry.cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // 24 hours
    }
}
