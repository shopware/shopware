<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Metric;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfig;

#[Package('core')]
readonly class Metric
{
    public string $name;

    public int|float $value;

    /**
     * @var array<non-empty-string, string|bool|float|int>
     */
    public array $labels;

    public Type $type;

    public string $description;

    public ?string $unit;

    /**
     * @internal
     */
    public function __construct(
        ConfiguredMetric $configuredMetric,
        MetricConfig $metricConfig
    ) {
        $this->name = $configuredMetric->name;
        $this->value = $configuredMetric->value instanceof \Closure ? \call_user_func($configuredMetric->value) : $configuredMetric->value;
        $this->labels = $this->removeDisallowedLabels($configuredMetric, $metricConfig);
        $this->type = $metricConfig->type;
        $this->description = $metricConfig->description;
        $this->unit = $metricConfig->unit;
    }

    /**
     * @return array<non-empty-string, string|bool|float|int>
     */
    private function removeDisallowedLabels(ConfiguredMetric $metric, MetricConfig $metricConfig): array
    {
        $allowedLabels = $metricConfig->labels;

        return array_filter(
            $metric->labels,
            fn (mixed $value, string $name) => isset($allowedLabels[$name]) && \in_array($value, $allowedLabels[$name]['allowed_values'] ?? [], true),
            \ARRAY_FILTER_USE_BOTH
        );
    }
}
