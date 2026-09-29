<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\PeriodicMetricCollectorInterface;

/**
 * Samples scheduled-task health for telemetry:
 *  - `scheduled_task.backlog.max_lateness_seconds`: how far the scheduler is behind, i.e. the maximum seconds a `scheduled`/`skipped` task has waited past its next execution time.
 *    A rising value flags a stalled or backed-up scheduler.
 *  - `scheduled_task.failed.count`: tasks stuck in the `failed` state, which never rerun on their own.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class ScheduledTaskHealthCollector implements PeriodicMetricCollectorInterface
{
    public function __construct(
        private readonly ScheduledTaskHealthGateway $gateway,
        private readonly ClockInterface $clock,
    ) {
    }

    public function collect(): iterable
    {
        yield new ConfiguredMetric(
            name: 'scheduled_task.backlog.max_lateness_seconds',
            value: $this->gateway->getMaxLatenessSeconds($this->clock->now()),
        );

        yield new ConfiguredMetric(
            name: 'scheduled_task.failed.count',
            value: $this->gateway->countFailed(),
        );
    }
}
