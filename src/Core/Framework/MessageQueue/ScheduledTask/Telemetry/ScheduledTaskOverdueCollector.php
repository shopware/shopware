<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\PeriodicMetricCollectorInterface;

/**
 * Counts scheduled tasks whose next execution time has passed while they are still `scheduled`, i.e. the
 * scheduler has not picked them up. A rising `scheduled_task.overdue.count` flags a stalled scheduler, independent of the message queue depth.
 *
 * Driven by the `telemetry.collect_periodic_metrics` scheduled task.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class ScheduledTaskOverdueCollector implements PeriodicMetricCollectorInterface
{
    public function __construct(
        private readonly ScheduledTaskOverdueGateway $gateway,
        private readonly ClockInterface $clock,
    ) {
    }

    public function collect(): iterable
    {
        yield new ConfiguredMetric(
            name: 'scheduled_task.overdue.count',
            value: $this->gateway->countOverdue($this->clock->now()),
        );
    }
}
