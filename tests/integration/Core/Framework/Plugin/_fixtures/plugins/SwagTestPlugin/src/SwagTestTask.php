<?php declare(strict_types=1);

namespace SwagTestPlugin;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class SwagTestTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'swag_test.test_task';
    }

    public static function getDefaultInterval(): int
    {
        return self::HOURLY;
    }
}
