<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\MetricInterface;

/**
 * @internal
 */
#[Package('core')]
interface MetricTransportInterface
{
    /**
     * @throws MetricNotSupportedException
     */
    public function emit(MetricInterface $metric): void;
}
