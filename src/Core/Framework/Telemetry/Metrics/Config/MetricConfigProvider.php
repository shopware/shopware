<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Config;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Telemetry\AppTelemetryConfigLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\TelemetryException;

/**
 * @internal
 *
 * @phpstan-import-type MetricDefinition from MetricConfig
 */
#[Package('framework')]
class MetricConfigProvider
{
    /**
     * @var array<string, MetricConfig>
     */
    private array $metricsByName;

    private bool $appValuesApplied = false;

    /**
     * @param array<string, MetricDefinition> $definitions
     * @param (\Closure(): AppTelemetryConfigLoader)|null $appConfigLoader injected as a service closure:
     *                                                                     an eager reference closes a container cycle (see LabelMappingRegistry)
     */
    public function __construct(
        private readonly array $definitions,
        private readonly ?\Closure $appConfigLoader = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $metricsByName = [];
        foreach ($definitions as $name => $definition) {
            $metricsByName[$name] = MetricConfig::fromDefinition($name, $definition);
        }

        $this->metricsByName = $metricsByName;
    }

    public function get(string $name): MetricConfig
    {
        $this->applyAppValues();

        return $this->metricsByName[$name] ?? throw TelemetryException::metricMissingConfiguration($name);
    }

    /**
     * @return array<MetricConfig>
     */
    public function all(): array
    {
        $this->applyAppValues();

        return array_values($this->metricsByName);
    }

    /**
     * Unions app-contributed label values (persisted at install, loaded fail-soft) into the compiled
     * config, once per process on first access. Entries that no longer match the compiled config — the
     * platform was upgraded underneath an installed app, or an operator locked the label — are dropped
     * with a debug log.
     */
    private function applyAppValues(): void
    {
        if ($this->appValuesApplied || $this->appConfigLoader === null) {
            return;
        }

        $this->appValuesApplied = true;

        foreach (($this->appConfigLoader)()->load()['labelValues'] as $metricName => $labels) {
            foreach ($labels as $labelName => $values) {
                $labelDefinition = $this->definitions[$metricName]['labels'][$labelName] ?? null;

                if ($labelDefinition === null) {
                    $this->logger?->debug(\sprintf(
                        'Dropped app telemetry values for unknown metric label "%s"/"%s".',
                        $metricName,
                        $labelName,
                    ));

                    continue;
                }

                if (($labelDefinition['allowed_values'] ?? []) !== [] || ($labelDefinition['add_allowed_values'] ?? []) === []) {
                    $this->logger?->debug(\sprintf(
                        'Dropped app telemetry values for metric label "%s"/"%s": the label is locked or takes no value contributions.',
                        $metricName,
                        $labelName,
                    ));

                    continue;
                }

                $metric = $this->metricsByName[$metricName];
                $labelConfig = $metric->labels[$labelName];

                $labels = $metric->labels;
                $labels[$labelName] = new LabelConfig(
                    allowedValues: array_values(array_unique(array_merge($labelConfig->allowedValues ?? [], $values))),
                    policy: $labelConfig->policy,
                );

                $this->metricsByName[$metricName] = new MetricConfig(
                    name: $metric->name,
                    description: $metric->description,
                    type: $metric->type,
                    enabled: $metric->enabled,
                    parameters: $metric->parameters,
                    labels: $labels,
                    unit: $metric->unit,
                );
            }
        }
    }
}
