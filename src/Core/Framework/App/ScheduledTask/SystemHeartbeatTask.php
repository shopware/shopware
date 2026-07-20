<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SystemHeartbeatTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'app.system_heartbeat';
    }

    public static function getDefaultInterval(): int
    {
        return self::WEEKLY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
