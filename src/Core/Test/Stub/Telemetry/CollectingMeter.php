<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Telemetry;

use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * Collects emitted metrics instead of processing them; every metric counts as enabled, so calculations behind gates run.
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 *
 * @final
 */
class CollectingMeter extends Meter
{
    /**
     * @var list<ConfiguredMetric>
     */
    private array $emitted = [];

    public function __construct()
    {
        // no parent dependencies: the stub never processes or forwards metrics
    }

    public function emit(ConfiguredMetric $metric): void
    {
        $this->emitted[] = $metric;
    }

    public function isEnabled(string $metric): bool
    {
        return true;
    }

    /**
     * All emitted metrics, optionally filtered by name.
     *
     * @return list<ConfiguredMetric>
     */
    public function getMetrics(?string $name = null): array
    {
        if ($name === null) {
            return $this->emitted;
        }

        return array_values(array_filter(
            $this->emitted,
            static fn (ConfiguredMetric $metric): bool => $metric->name === $name,
        ));
    }

    /**
     * The single emitted metric with the given name; throws when it is absent or was emitted more than once.
     */
    public function getMetric(string $name): ConfiguredMetric
    {
        $metrics = $this->getMetrics($name);

        return match (\count($metrics)) {
            0 => throw new \RuntimeException(\sprintf('Metric "%s" was not emitted', $name)),
            1 => $metrics[0],
            default => throw new \RuntimeException(\sprintf('Metric "%s" was emitted %d times, expected once', $name, \count($metrics))),
        };
    }

    /**
     * @return list<string>
     */
    public function getMetricNames(): array
    {
        return array_map(static fn (ConfiguredMetric $metric): string => $metric->name, $this->emitted);
    }

    /**
     * Labels of the single emitted metric with the given name, closures resolved the way the real Meter resolves them.
     *
     * @return array<non-empty-string, string|bool|float|int>
     */
    public function getLabels(string $name): array
    {
        $labels = $this->getMetric($name)->labels;

        return $labels instanceof \Closure ? $labels() : $labels;
    }
}
