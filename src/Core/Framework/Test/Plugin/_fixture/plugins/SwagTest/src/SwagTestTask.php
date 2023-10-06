<?php declare(strict_types=1);

namespace SwagTest;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class SwagTestTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'swag_test.test_task';
    }

    public static function getDefaultInterval(): int
    {
        return 3600;
    }
}
