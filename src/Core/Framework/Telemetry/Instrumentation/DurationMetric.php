<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Instrumentation;

use Shopware\Core\Framework\Log\Package;

/**
 * Labels may be a closure receiving the instrumented callback's outcome: its return result (null when it
 * threw) and the thrown exception (null on success). `Telemetry::instrument()` captures both and the
 * `Meter` resolves the closure after the per-metric `enabled` gate, so labels calculation costs
 * nothing while the metric is off.
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 *
 * @codeCoverageIgnore - value object
 *
 * @template-contravariant T the instrumented callback's return type, so label closures can type their parameter
 */
#[Package('framework')]
final readonly class DurationMetric
{
    /**
     * @param array<non-empty-string, string|bool|float|int>|(\Closure(T|null, \Throwable|null): array<non-empty-string, string|bool|float|int>) $labels
     */
    public function __construct(
        public string $name,
        public array|\Closure $labels = [],
    ) {
    }
}
