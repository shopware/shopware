<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\MetricInterface;

/**
 * @internal
 */
#[Package('core')]
class Meter
{
    /**
     * @param \Traversable<MetricTransportInterface> $transports
     */
    public function __construct(
        private readonly \Traversable $transports,
        private readonly LoggerInterface $logger
    ) {
    }

    public function emit(MetricInterface $metric): void
    {
        if (!Feature::isActive('TELEMETRY_METRICS')) {
            return;
        }

        foreach ($this->transports as $transport) {
            try {
                $transport->emit($metric);
            } catch (MetricNotSupportedException $exception) {
                $this->logger->error(
                    $exception->getMessage(),
                    ['exception' => $exception]
                );
            } catch (\Throwable $e) {
                // emitting the metric should not break the application no matter the underlying transport issue.
                $this->logger->error(
                    \sprintf('Failed to emit metric via transport %s', $transport::class),
                    ['exception' => $e]
                );
            }
        }
    }
}
