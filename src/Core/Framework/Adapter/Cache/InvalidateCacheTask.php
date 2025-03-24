<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('framework')]
class InvalidateCacheTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'shopware.invalidate_cache';
    }

    public static function getDefaultInterval(): int
    {
        // Run every five minutes
        return self::MINUTELY * 5;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
