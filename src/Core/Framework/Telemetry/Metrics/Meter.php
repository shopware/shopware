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
 * @internal
 */
#[Package('core')]
class Meter
{
    /**
     * @param TransportCollection<MetricTransportInterface> $transports
     */
    public function __construct(
        private readonly TransportCollection $transports,
        private readonly MetricConfigProvider $metricConfigProvider,
        private readonly LoggerInterface $logger,
        private readonly string $environment
    ) {
    }

    public function emit(ConfiguredMetric $metric): void
    {
        if (!Feature::isActive('TELEMETRY_METRICS')) {
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

    private function process(ConfiguredMetric $metric): ?Metric
    {
        try {
            $metricConfig = $this->metricConfigProvider->get($metric->name);
            if (!$metricConfig->enabled) {
                return null;
            }

            return new Metric(configuredMetric: $metric, metricConfig: $metricConfig);
        } catch (MissingMetricConfigurationException $exception) {
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
