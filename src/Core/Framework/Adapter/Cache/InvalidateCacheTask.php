<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class InvalidateCacheTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'shopware.invalidate_cache';
    }

    public static function getDefaultInterval(): int
    {
        return 20;
    }
}
