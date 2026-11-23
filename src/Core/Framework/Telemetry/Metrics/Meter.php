<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfigProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MissingMetricConfigurationException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;

/**
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class Meter
{
    /**
     * @internal
     *
     * @param TransportCollection<MetricTransportInterface> $transports
     */
    public function __construct(
        private readonly TransportCollection $transports,
        private readonly MetricConfigProvider $metricConfigProvider,
        private readonly MetricLabelProcessor $labelProcessor,
        private readonly LoggerInterface $logger,
        private readonly string $environment,
        private readonly bool $enabled,
    ) {
    }

    public function emit(ConfiguredMetric $metric): void
    {
        if (!$this->isEnabled($metric->name)) {
            return;
        }

        $metric = $this->process($metric);
        if ($metric === null) {
            return;
        }

        foreach ($this->transports as $transport) {
            $this->doEmitVia($metric, $transport);
        }
    }

    /**
     * Whether a metric would actually be emitted: the global switch, the feature flag and the
     * per-metric `enabled` config are all checked. Call it before a measurement whose setup
     * (timers, label derivation, value computation) is too expensive to run for a discarded metric.
     *
     * A metric without a yaml definition behaves like in `emit()`: throws in dev/test, logs and
     * returns false in prod.
     */
    public function isEnabled(string $metric): bool
    {
        if (!$this->enabled || !Feature::isActive('TELEMETRY_METRICS')) {
            return false;
        }

        try {
            return $this->metricConfigProvider->get($metric)->enabled;
        } catch (MissingMetricConfigurationException $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            if ($this->environment === 'dev' || $this->environment === 'test') {
                throw $exception;
            }

            return false;
        }
    }

    private function process(ConfiguredMetric $metric): ?Metric
    {
        // the gate in emit() already handled a missing definition and a disabled metric
        $metricConfig = $this->metricConfigProvider->get($metric->name);

        try {
            // labels before value, so a metric discarded by label policy never computes its value
            $labels = $metric->labels instanceof \Closure ? ($metric->labels)() : $metric->labels;
            $processedLabels = $this->labelProcessor->process($metricConfig, $labels);
            if ($processedLabels === null) {
                return null;
            }

            $value = $metric->value instanceof \Closure ? ($metric->value)() : $metric->value;

            return Metric::fromConfig(metricConfig: $metricConfig, labels: $processedLabels, value: $value);
        } catch (\Throwable $exception) {
            // this has to be silenced so metrics failing closures do not break critical code/do not replace
            // original exception when emitted in finally blocks
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            if ($this->environment === 'dev' || $this->environment === 'test') {
                throw $exception;
            }

            return null;
        }
    }

    private function doEmitVia(Metric $metric, MetricTransportInterface $transport): void
    {
        try {
            $transport->emit($metric);
        } catch (\Throwable $e) {
            $this->logger->warning(
                $e instanceof MetricNotSupportedException ? $e->getMessage() : \sprintf('Failed to emit metric via transport %s', $transport::class),
                ['exception' => $e]
            );

            if ($this->environment === 'dev' || $this->environment === 'test') {
                throw $e;
            }
        }
    }
}
