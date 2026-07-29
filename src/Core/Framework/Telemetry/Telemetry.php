<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Instrumentation\DurationMetric;
use Shopware\Core\Framework\Telemetry\Instrumentation\Span;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Profiling\Profiler;

/**
 * High-level facade combining metric emission and profiler spans behind a single DI-injectable
 * service. Use `emit()` for regular pre-computed metrics and `instrument()` to measure operation
 * duration and/or create a profiler span around a callback.
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class Telemetry
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Meter $meter,
        private readonly string $environment,
    ) {
    }

    public function emit(ConfiguredMetric $metric): void
    {
        $this->meter->emit($metric);
    }

    /**
     * Execute the callback and optionally record its duration as a histogram metric and/or wrap it
     * in a profiler span. Duration is always emitted in milliseconds.
     *
     * Span and metric are measured independently: the profiler span captures only the callback
     * execution (not the metric emission), and the duration timer captures only the callback
     * (not the profiler start/stop overhead).
     *
     * @template T
     *
     * @param \Closure(): T $callback
     *
     * @return T
     */
    public function instrument(
        \Closure $callback,
        ?DurationMetric $metric = null,
        ?Span $span = null,
    ): mixed {
        if ($metric === null && $span === null) {
            if ($this->environment === 'dev' || $this->environment === 'test') {
                throw TelemetryException::instrumentWithoutMetricOrSpan();
            }

            return $callback();
        }

        // Profiler::start/stop is used instead of Profiler::trace() so that span and metric
        // remain orthogonal: the span brackets only the callback (metric emission stays outside
        // the span), and the duration timer brackets only the callback (profiler overhead stays
        // outside the metric). Wrapping the callback in Profiler::trace() would couple the two.
        if ($span !== null) {
            Profiler::start($span->name, $span->category, $span->tags);
        }

        $start = $metric !== null ? hrtime(true) : null;
        $durationMs = null;

        try {
            return $callback();
        } finally {
            if ($start !== null) {
                $durationMs = (hrtime(true) - $start) / 1_000_000;
            }

            if ($span !== null) {
                Profiler::stop($span->name);
            }

            if ($metric !== null && $durationMs !== null) {
                $this->meter->emit(new ConfiguredMetric(
                    name: $metric->name,
                    value: $durationMs,
                    labels: $metric->labels,
                ));
            }
        }
    }
}
