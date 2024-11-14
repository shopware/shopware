<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Package('core')]
class InvalidateCacheTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'shopware.invalidate_cache';
    }

    public static function getDefaultInterval(): int
    {
        if (!Feature::isActive('cache_rework')) {
            return 20;
        }

        // Run every 5 mins
        return self::MINUTELY * 5;
    }

    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return Feature::isActive('cache_rework') || $bag->get('shopware.cache.invalidation.delay') > 0;
    }
}
