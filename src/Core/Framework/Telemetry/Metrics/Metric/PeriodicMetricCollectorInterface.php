<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Metric;

use Shopware\Core\Framework\Log\Package;

/**
 * Implement this interface for metrics that should be collected on a schedule rather than emitted
 * inline — typically expensive computations (database aggregations) or low-frequency information
 * metrics. Tagged services are collected by the `telemetry.collect_periodic_metrics` scheduled task
 * and emitted through the standard Meter::emit() path.
 *
 * For metrics with a natural per-event emission point (counters, histograms tied to subscribers),
 * keep using `Meter::emit()` directly — do not route those through this interface.
 *
 * Plugins needing a different frequency should register their own scheduled task.
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
interface PeriodicMetricCollectorInterface
{
    /**
     * @return iterable<ConfiguredMetric>
     */
    public function collect(): iterable;
}
