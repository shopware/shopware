<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

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
        return 20;
    }

    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return $bag->get('shopware.cache.invalidation.delay') > 0;
    }
}
