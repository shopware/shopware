<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SystemCheckTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'system_check.run';
    }

    public static function getDefaultInterval(): int
    {
        return self::HOURLY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
