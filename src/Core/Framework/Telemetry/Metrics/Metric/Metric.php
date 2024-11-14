<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Metric;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;

#[Package('core')]
readonly class Metric
{
    final private function __construct(
        public string $name,
        public int|float $value,
        /**
         * @var array<non-empty-string, string|bool|float|int>
         */
        public array $labels,
        public Type $type,
        public string $description,
        public ?string $unit,
    ) {
    }

    /**
     * @internal
     */
    public static function fromConfigured(
        ConfiguredMetric $configuredMetric,
        MetricConfig $metricConfig
    ): self {
        return new self(
            name: $configuredMetric->name,
            value: $configuredMetric->value instanceof \Closure ? \call_user_func($configuredMetric->value) : $configuredMetric->value,
            labels: self::removeDisallowedLabels($configuredMetric, $metricConfig),
            type: $metricConfig->type,
            description: $metricConfig->description,
            unit: $metricConfig->unit,
        );
    }

    /**
     * This factory is intended to simplify testing of transports. To emit metrics please use the Meter class and ConfiguredMetric class.
     *
     * @experimental feature:TELEMETRY_METRICS stableVersion:v6.7.0
     *
     * @param array{ name: string, type: Type, value: int|float, labels?: array<non-empty-string, string|bool|float|int>, description?: string, unit?: string|null } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: $data['type'] ?? Type::COUNTER,
            value: $data['value'],
            labels: $data['labels'] ?? [],
            description: $data['description'] ?? '',
            unit: $data['unit'] ?? null,
        );
    }

    /**
     * @return array<non-empty-string, string|bool|float|int>
     */
    private static function removeDisallowedLabels(ConfiguredMetric $metric, MetricConfig $metricConfig): array
    {
        $allowedLabels = $metricConfig->labels;

        return array_filter(
            $metric->labels,
            fn (mixed $value, string $name) => isset($allowedLabels[$name]) && \in_array($value, $allowedLabels[$name]['allowed_values'] ?? [], true),
            \ARRAY_FILTER_USE_BOTH
        );
    }
}
