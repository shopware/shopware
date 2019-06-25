<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Logging\ScheduledTask;

use Shopware\Core\Framework\ScheduledTask\ScheduledTask;

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
