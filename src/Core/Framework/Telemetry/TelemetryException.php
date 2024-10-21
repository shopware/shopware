<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MetricNotSupportedException;
use Shopware\Core\Framework\Telemetry\Metrics\Exception\MissingMetricConfigurationException;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\Metric;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;

/**
 * @internal
 */
#[Package('core')]
abstract class TelemetryException extends HttpException
{
    public static function metricNotSupported(
        Metric $metric,
        MetricTransportInterface $transport
    ): MetricNotSupportedException {
        return new MetricNotSupportedException(
            metric: $metric,
            transport: $transport,
            message: \sprintf('Metric %s, not supported by transport %s', $metric::class, $transport::class),
        );
    }

    public static function metricMissingConfiguration(string $metric): MissingMetricConfigurationException
    {
        return new MissingMetricConfigurationException(
            metric: $metric,
            message: \sprintf('Missing configuration for metric %s', $metric),
        );
    }
}
