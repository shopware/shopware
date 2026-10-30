<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Metric;

use Shopware\Core\Framework\Log\Package;

/**
 * All objects instantiated from this class should map to a metric that's preconfigured in `config/packages/telemetry.yaml`.
 * The mapping is done via the `name` property as an identifier.
 *
 * Value and labels may be argument-less closures. The `Meter` resolves them after the per-metric `enabled` gate,
 * with labels before value, so a metric discarded by label policy never computes its value.
 *
 * Use closures when the labels/values calculation costs are high.
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
readonly class ConfiguredMetric
{
    public function __construct(
        public string $name,
        /**
         * @var int|float|(\Closure():int)|(\Closure():float)
         */
        public int|float|\Closure $value,
        /**
         * @var array<non-empty-string, string|bool|float|int>|(\Closure(): array<non-empty-string, string|bool|float|int>)
         */
        public array|\Closure $labels = [],
    ) {
    }
}
