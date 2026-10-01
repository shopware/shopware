<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @internal
 */
#[Package('framework')]
class CollectPeriodicMetricsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'telemetry.collect_periodic_metrics';
    }

    public static function getDefaultInterval(): int
    {
        return 5 * self::MINUTELY;
    }

    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return (bool) $bag->get('shopware.telemetry.metrics.enabled');
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
