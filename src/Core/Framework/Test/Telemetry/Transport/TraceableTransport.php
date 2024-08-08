<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Telemetry\Transport;

use Shopware\Core\Framework\Telemetry\Metrics\Metric\MetricInterface;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;

/**
 * @internal
 */
class TraceableTransport implements MetricTransportInterface
{
    /**
     * @var MetricInterface[]
     */
    private array $metrics = [];

    public function emit(MetricInterface $metric): void
    {
        $this->metrics[] = $metric;
    }

    /**
     * @return MetricInterface[]
     */
    public function getEmittedMetrics(): array
    {
        return $this->metrics;
    }

    public function reset(): self
    {
        $this->metrics = [];

        return $this;
    }
}
