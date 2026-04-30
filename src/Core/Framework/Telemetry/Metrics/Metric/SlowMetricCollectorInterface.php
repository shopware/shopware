<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Metric;

use Shopware\Core\Framework\Log\Package;

/**
 * Implement this interface for metrics that require expensive computation (e.g. database
 * aggregations). Tagged services are collected by a scheduled task at a configurable interval
 * and emitted through the standard Meter::emit() path.
 *
 * Plugins needing a different frequency should register their own scheduled task.
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
interface SlowMetricCollectorInterface
{
    /**
     * @return iterable<ConfiguredMetric>
     */
    public function collect(): iterable;
}
