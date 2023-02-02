<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class DeleteCascadeAppsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'app_delete';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // 1 Day
    }
}
