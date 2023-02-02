<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Version\Cleanup;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CleanupVersionTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'version.cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; //24 hours
    }
}
