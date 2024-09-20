<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Metric;

use Shopware\Core\Framework\Log\Package;

/**
 * All objects instantiated from this class should map to a metric that's preconfigured in `config/packages/telemetry.yaml`.
 * The mapping is done via the `name` property as an identifier.
 *
 * @internal
 */
#[Package('core')]
readonly class ConfiguredMetric
{
    public function __construct(
        public string $name,
        /**
         * @var int|float|(\Closure():int)|(\Closure():float)
         */
        public int|float|\Closure $value,
        /**
         * @var array<non-empty-string, string|bool|float|int>
         */
        public array $labels = [],
    ) {
    }
}
